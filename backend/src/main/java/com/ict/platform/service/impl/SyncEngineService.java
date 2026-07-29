package com.ict.platform.service.impl;

import com.ict.platform.entity.Project;
import com.ict.platform.entity.SyncLog;
import com.ict.platform.entity.SyncQueue;
import com.ict.platform.integration.zoho.ZohoCrmService;
import com.ict.platform.repository.ProjectRepository;
import com.ict.platform.repository.SyncLogRepository;
import com.ict.platform.repository.SyncQueueRepository;
import com.ict.platform.websocket.NotificationService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.data.domain.PageRequest;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDateTime;
import java.util.List;

@Service
@RequiredArgsConstructor
@Slf4j
public class SyncEngineService {

    private static final int BATCH_SIZE = 20;
    private static final int MAX_RETRIES = 3;

    private final SyncQueueRepository syncQueueRepository;
    private final SyncLogRepository syncLogRepository;
    private final ProjectRepository projectRepository;
    private final ZohoCrmService zohoCrmService;
    private final NotificationService notificationService;

    @Scheduled(fixedDelay = 15 * 60 * 1000) // Every 15 minutes
    @Transactional
    public void processSyncQueue() {
        log.debug("Processing sync queue...");

        List<SyncQueue> items = syncQueueRepository.findPendingItems(
                LocalDateTime.now(),
                PageRequest.of(0, BATCH_SIZE)
        );

        if (items.isEmpty()) {
            return;
        }

        log.info("Processing {} sync items", items.size());

        for (SyncQueue item : items) {
            processSyncItem(item);
        }
    }

    @Scheduled(cron = "0 0 2 * * *") // Daily at 2 AM
    @Transactional
    public void cleanupOldLogs() {
        LocalDateTime cutoff = LocalDateTime.now().minusDays(30);
        int deleted = syncLogRepository.deleteOlderThan(cutoff);
        if (deleted > 0) {
            log.info("Cleaned up {} old sync logs", deleted);
        }
    }

    private void processSyncItem(SyncQueue item) {
        item.setStatus(SyncQueue.SyncStatus.PROCESSING);
        syncQueueRepository.save(item);

        long startTime = System.currentTimeMillis();
        SyncLog.SyncStatus status = SyncLog.SyncStatus.SUCCESS;
        String errorMessage = null;

        try {
            performSync(item);
            item.setStatus(SyncQueue.SyncStatus.COMPLETED);

            notificationService.sendSyncStatus(item.getEntityType(), item.getEntityId(), "COMPLETED");
        } catch (Exception e) {
            log.error("Sync failed for {} {} ({}): {}", item.getEntityType(), item.getEntityId(),
                    item.getZohoService(), e.getMessage());

            errorMessage = e.getMessage();
            status = SyncLog.SyncStatus.ERROR;
            item.setErrorMessage(e.getMessage());
            item.setRetryCount(item.getRetryCount() + 1);

            if (item.getRetryCount() >= MAX_RETRIES) {
                item.setStatus(SyncQueue.SyncStatus.FAILED);
                notificationService.sendSyncStatus(item.getEntityType(), item.getEntityId(), "FAILED");
            } else {
                item.setStatus(SyncQueue.SyncStatus.PENDING);
                item.setNextRetryAt(LocalDateTime.now().plusMinutes(15L * item.getRetryCount()));
                notificationService.sendSyncStatus(item.getEntityType(), item.getEntityId(), "RETRYING");
            }
        }

        syncQueueRepository.save(item);

        SyncLog logEntry = SyncLog.builder()
                .entityType(item.getEntityType())
                .entityId(item.getEntityId())
                .action(item.getAction())
                .zohoService(item.getZohoService())
                .direction(SyncLog.SyncDirection.OUTBOUND)
                .status(status)
                .errorMessage(errorMessage)
                .durationMs(System.currentTimeMillis() - startTime)
                .processedAt(LocalDateTime.now())
                .build();
        syncLogRepository.save(logEntry);
    }

    private void performSync(SyncQueue item) {
        if ("project".equals(item.getEntityType()) && item.getZohoService() == SyncQueue.ZohoService.CRM) {
            projectRepository.findById(item.getEntityId()).ifPresent(project -> {
                switch (item.getAction()) {
                    case CREATE -> {
                        zohoCrmService.createDeal(project);
                    }
                    case UPDATE -> {
                        if (project.getZohoCrmDealId() != null) {
                            zohoCrmService.updateDeal(project.getZohoCrmDealId(), project);
                        } else {
                            zohoCrmService.createDeal(project);
                        }
                    }
                    case DELETE -> {
                        if (project.getZohoCrmDealId() != null) {
                            zohoCrmService.deleteDeal(project.getZohoCrmDealId());
                        }
                    }
                }
            });
        }
    }
}
