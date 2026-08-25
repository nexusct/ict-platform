package com.ict.platform.entity;

import jakarta.persistence.*;
import lombok.*;

import java.time.LocalDateTime;

@Entity
@Table(name = "sync_log")
@Data
@EqualsAndHashCode(callSuper = true)
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class SyncLog extends BaseEntity {

    @Column(name = "entity_type", nullable = false)
    private String entityType;

    @Column(name = "entity_id", nullable = false)
    private Long entityId;

    @Enumerated(EnumType.STRING)
    private SyncQueue.SyncAction action;

    @Enumerated(EnumType.STRING)
    @Column(name = "zoho_service")
    private SyncQueue.ZohoService zohoService;

    @Enumerated(EnumType.STRING)
    private SyncDirection direction;

    @Enumerated(EnumType.STRING)
    @Builder.Default
    private SyncStatus status = SyncStatus.SUCCESS;

    @Column(name = "request_data", columnDefinition = "TEXT")
    private String requestData;

    @Column(name = "response_data", columnDefinition = "TEXT")
    private String responseData;

    @Column(name = "error_message", columnDefinition = "TEXT")
    private String errorMessage;

    @Column(name = "duration_ms")
    private Long durationMs;

    @Column(name = "processed_at")
    private LocalDateTime processedAt;

    public enum SyncDirection {
        INBOUND, OUTBOUND
    }

    public enum SyncStatus {
        SUCCESS, ERROR
    }
}
