package com.ict.platform.dto.request;

import com.ict.platform.entity.Project;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import lombok.Data;

import java.math.BigDecimal;
import java.time.LocalDate;

@Data
public class ProjectRequest {

    @NotBlank(message = "Project name is required")
    private String name;

    private String description;

    @NotNull(message = "Status is required")
    private Project.ProjectStatus status;

    private Project.Priority priority;

    private LocalDate startDate;

    private LocalDate endDate;

    private BigDecimal budget;

    private String clientName;

    private String clientEmail;

    private String clientPhone;

    private String siteAddress;

    private BigDecimal siteLatitude;

    private BigDecimal siteLongitude;

    private Long assignedTechnicianId;

    private Long projectManagerId;

    private Integer completionPercentage;

    private String notes;
}
