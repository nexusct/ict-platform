package com.ict.platform.repository;

import com.ict.platform.entity.SyncLog;
import com.ict.platform.entity.SyncQueue;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Modifying;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import org.springframework.stereotype.Repository;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDateTime;

@Repository
public interface SyncLogRepository extends JpaRepository<SyncLog, Long> {

    Page<SyncLog> findByEntityTypeAndEntityId(String entityType, Long entityId, Pageable pageable);

    Page<SyncLog> findByZohoService(SyncQueue.ZohoService zohoService, Pageable pageable);

    Page<SyncLog> findByStatus(SyncLog.SyncStatus status, Pageable pageable);

    @Modifying
    @Transactional
    @Query("DELETE FROM SyncLog s WHERE s.createdAt < :cutoffDate")
    int deleteOlderThan(@Param("cutoffDate") LocalDateTime cutoffDate);
}
