package com.ict.platform.repository;

import com.ict.platform.entity.SyncQueue;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import org.springframework.stereotype.Repository;

import java.time.LocalDateTime;
import java.util.List;

@Repository
public interface SyncQueueRepository extends JpaRepository<SyncQueue, Long> {

    @Query("SELECT s FROM SyncQueue s WHERE s.status = 'PENDING' AND " +
           "(s.nextRetryAt IS NULL OR s.nextRetryAt <= :now) " +
           "ORDER BY s.priority ASC, s.createdAt ASC")
    List<SyncQueue> findPendingItems(@Param("now") LocalDateTime now, Pageable pageable);

    Page<SyncQueue> findByStatus(SyncQueue.SyncStatus status, Pageable pageable);

    List<SyncQueue> findByEntityTypeAndEntityId(String entityType, Long entityId);

    long countByStatus(SyncQueue.SyncStatus status);
}
