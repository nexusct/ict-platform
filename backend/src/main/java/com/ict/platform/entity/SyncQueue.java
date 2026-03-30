package com.ict.platform.entity;

import jakarta.persistence.*;
import lombok.*;

import java.time.LocalDateTime;

@Entity
@Table(name = "sync_queue")
@Data
@EqualsAndHashCode(callSuper = true)
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class SyncQueue extends BaseEntity {

    @Column(name = "entity_type", nullable = false)
    private String entityType;

    @Column(name = "entity_id", nullable = false)
    private Long entityId;

    @Enumerated(EnumType.STRING)
    @Column(name = "action", nullable = false)
    private SyncAction action;

    @Enumerated(EnumType.STRING)
    @Column(name = "zoho_service", nullable = false)
    private ZohoService zohoService;

    @Builder.Default
    private Integer priority = 5;

    @Enumerated(EnumType.STRING)
    @Builder.Default
    private SyncStatus status = SyncStatus.PENDING;

    @Column(name = "retry_count")
    @Builder.Default
    private Integer retryCount = 0;

    @Column(name = "max_retries")
    @Builder.Default
    private Integer maxRetries = 3;

    @Column(name = "next_retry_at")
    private LocalDateTime nextRetryAt;

    @Column(columnDefinition = "TEXT")
    private String payload;

    @Column(name = "error_message", columnDefinition = "TEXT")
    private String errorMessage;

    public enum SyncAction {
        CREATE, UPDATE, DELETE
    }

    public enum ZohoService {
        CRM, FSM, BOOKS, PEOPLE, DESK
    }

    public enum SyncStatus {
        PENDING, PROCESSING, COMPLETED, FAILED
    }
}
