package com.ict.platform.dto.request;

import jakarta.validation.constraints.NotNull;
import lombok.Data;

import java.math.BigDecimal;
import java.time.LocalDateTime;

@Data
public class TimeEntryRequest {

    @NotNull(message = "Project ID is required")
    private Long projectId;

    private LocalDateTime clockIn;

    private LocalDateTime clockOut;

    private BigDecimal hourlyRate;

    private String description;

    private String taskType;

    private BigDecimal clockInLatitude;

    private BigDecimal clockInLongitude;

    private Boolean billable;
}
