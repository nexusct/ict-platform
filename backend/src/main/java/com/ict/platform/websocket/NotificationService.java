package com.ict.platform.websocket;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.messaging.simp.SimpMessagingTemplate;
import org.springframework.stereotype.Service;

import java.time.LocalDateTime;
import java.util.Map;

@Service
@RequiredArgsConstructor
@Slf4j
public class NotificationService {

    private final SimpMessagingTemplate messagingTemplate;

    public void sendProjectUpdate(Long projectId, String event, Object data) {
        Map<String, Object> notification = Map.of(
                "type", "PROJECT_UPDATE",
                "projectId", projectId,
                "event", event,
                "data", data,
                "timestamp", LocalDateTime.now().toString()
        );
        messagingTemplate.convertAndSend("/topic/projects/" + projectId, notification);
        log.debug("Sent project update notification for project {}: {}", projectId, event);
    }

    public void sendInventoryAlert(Long itemId, String alertType, Object data) {
        Map<String, Object> notification = Map.of(
                "type", "INVENTORY_ALERT",
                "itemId", itemId,
                "alertType", alertType,
                "data", data,
                "timestamp", LocalDateTime.now().toString()
        );
        messagingTemplate.convertAndSend("/topic/inventory/alerts", notification);
        log.debug("Sent inventory alert for item {}: {}", itemId, alertType);
    }

    public void sendSyncStatus(String entityType, Long entityId, String status) {
        Map<String, Object> notification = Map.of(
                "type", "SYNC_STATUS",
                "entityType", entityType,
                "entityId", entityId,
                "status", status,
                "timestamp", LocalDateTime.now().toString()
        );
        messagingTemplate.convertAndSend("/topic/sync", notification);
        log.debug("Sent sync status for {} {}: {}", entityType, entityId, status);
    }

    public void sendUserNotification(String username, String type, String message, Object data) {
        Map<String, Object> notification = Map.of(
                "type", type,
                "message", message,
                "data", data != null ? data : Map.of(),
                "timestamp", LocalDateTime.now().toString()
        );
        messagingTemplate.convertAndSendToUser(username, "/queue/notifications", notification);
        log.debug("Sent notification to user {}: {}", username, type);
    }

    public void broadcastDashboardUpdate(Object dashboardData) {
        Map<String, Object> notification = Map.of(
                "type", "DASHBOARD_UPDATE",
                "data", dashboardData,
                "timestamp", LocalDateTime.now().toString()
        );
        messagingTemplate.convertAndSend("/topic/dashboard", notification);
    }
}
