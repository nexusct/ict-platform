package com.ict.platform.entity;

import jakarta.persistence.*;
import lombok.*;

import java.math.BigDecimal;
import java.time.LocalDateTime;

@Entity
@Table(name = "time_entries")
@Data
@EqualsAndHashCode(callSuper = true)
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class TimeEntry extends BaseEntity {

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "project_id", nullable = false)
    private Project project;

    @Column(name = "user_id", nullable = false)
    private Long userId;

    @Column(name = "clock_in", nullable = false)
    private LocalDateTime clockIn;

    @Column(name = "clock_out")
    private LocalDateTime clockOut;

    @Column(name = "hours_worked", precision = 6, scale = 2)
    private BigDecimal hoursWorked;

    @Column(name = "overtime_hours", precision = 6, scale = 2)
    @Builder.Default
    private BigDecimal overtimeHours = BigDecimal.ZERO;

    @Column(name = "hourly_rate", precision = 10, scale = 2)
    private BigDecimal hourlyRate;

    @Enumerated(EnumType.STRING)
    @Builder.Default
    private TimeEntryStatus status = TimeEntryStatus.ACTIVE;

    @Column(columnDefinition = "TEXT")
    private String description;

    @Column(name = "task_type")
    private String taskType;

    @Column(name = "clock_in_latitude", precision = 10, scale = 7)
    private BigDecimal clockInLatitude;

    @Column(name = "clock_in_longitude", precision = 10, scale = 7)
    private BigDecimal clockInLongitude;

    @Column(name = "clock_out_latitude", precision = 10, scale = 7)
    private BigDecimal clockOutLatitude;

    @Column(name = "clock_out_longitude", precision = 10, scale = 7)
    private BigDecimal clockOutLongitude;

    @Column(name = "zoho_people_entry_id")
    private String zohoPeopleEntryId;

    @Column(name = "is_billable")
    @Builder.Default
    private Boolean billable = true;

    @Column(name = "approved_by")
    private Long approvedBy;

    @Column(name = "approved_at")
    private LocalDateTime approvedAt;

    public enum TimeEntryStatus {
        ACTIVE, COMPLETED, APPROVED, REJECTED
    }
}
