package com.ict.platform.service.impl;

import com.ict.platform.dto.request.TimeEntryRequest;
import com.ict.platform.entity.Project;
import com.ict.platform.entity.TimeEntry;
import com.ict.platform.exception.BusinessException;
import com.ict.platform.exception.ResourceNotFoundException;
import com.ict.platform.repository.ProjectRepository;
import com.ict.platform.repository.TimeEntryRepository;
import com.ict.platform.service.TimeEntryService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.time.Duration;
import java.time.LocalDateTime;
import java.util.List;

@Service
@RequiredArgsConstructor
@Slf4j
public class TimeEntryServiceImpl implements TimeEntryService {

    private final TimeEntryRepository timeEntryRepository;
    private final ProjectRepository projectRepository;

    @Override
    @Transactional
    public TimeEntry clockIn(Long userId, Long projectId, BigDecimal latitude, BigDecimal longitude, String description) {
        timeEntryRepository.findActiveEntryByUserId(userId).ifPresent(e -> {
            throw new BusinessException("User " + userId + " is already clocked in");
        });

        Project project = projectRepository.findById(projectId)
                .orElseThrow(() -> new ResourceNotFoundException("Project not found: " + projectId));

        TimeEntry entry = TimeEntry.builder()
                .project(project)
                .userId(userId)
                .clockIn(LocalDateTime.now())
                .status(TimeEntry.TimeEntryStatus.ACTIVE)
                .clockInLatitude(latitude)
                .clockInLongitude(longitude)
                .description(description)
                .billable(true)
                .build();

        entry = timeEntryRepository.save(entry);
        log.info("User {} clocked in on project {}", userId, projectId);
        return entry;
    }

    @Override
    @Transactional
    public TimeEntry clockOut(Long userId, BigDecimal latitude, BigDecimal longitude) {
        TimeEntry entry = timeEntryRepository.findActiveEntryByUserId(userId)
                .orElseThrow(() -> new BusinessException("User " + userId + " is not clocked in"));

        LocalDateTime clockOut = LocalDateTime.now();
        entry.setClockOut(clockOut);
        entry.setClockOutLatitude(latitude);
        entry.setClockOutLongitude(longitude);
        entry.setStatus(TimeEntry.TimeEntryStatus.COMPLETED);

        Duration duration = Duration.between(entry.getClockIn(), clockOut);
        BigDecimal hours = BigDecimal.valueOf(duration.toMinutes())
                .divide(BigDecimal.valueOf(60), 2, RoundingMode.HALF_UP);
        entry.setHoursWorked(hours);

        BigDecimal overtimeHours = BigDecimal.ZERO;
        if (hours.compareTo(BigDecimal.valueOf(8)) > 0) {
            overtimeHours = hours.subtract(BigDecimal.valueOf(8));
        }
        entry.setOvertimeHours(overtimeHours);

        entry = timeEntryRepository.save(entry);
        log.info("User {} clocked out, worked {} hours", userId, hours);
        return entry;
    }

    @Override
    @Transactional
    public TimeEntry createTimeEntry(Long userId, TimeEntryRequest request) {
        Project project = projectRepository.findById(request.getProjectId())
                .orElseThrow(() -> new ResourceNotFoundException("Project not found: " + request.getProjectId()));

        TimeEntry entry = TimeEntry.builder()
                .project(project)
                .userId(userId)
                .clockIn(request.getClockIn() != null ? request.getClockIn() : LocalDateTime.now())
                .clockOut(request.getClockOut())
                .hourlyRate(request.getHourlyRate())
                .description(request.getDescription())
                .taskType(request.getTaskType())
                .clockInLatitude(request.getClockInLatitude())
                .clockInLongitude(request.getClockInLongitude())
                .billable(request.getBillable() != null ? request.getBillable() : true)
                .status(TimeEntry.TimeEntryStatus.COMPLETED)
                .build();

        if (request.getClockIn() != null && request.getClockOut() != null) {
            Duration duration = Duration.between(request.getClockIn(), request.getClockOut());
            BigDecimal hours = BigDecimal.valueOf(duration.toMinutes())
                    .divide(BigDecimal.valueOf(60), 2, RoundingMode.HALF_UP);
            entry.setHoursWorked(hours);
        }

        return timeEntryRepository.save(entry);
    }

    @Override
    @Transactional
    public TimeEntry updateTimeEntry(Long id, TimeEntryRequest request) {
        TimeEntry entry = getTimeEntryById(id);

        if (request.getProjectId() != null) {
            Project project = projectRepository.findById(request.getProjectId())
                    .orElseThrow(() -> new ResourceNotFoundException("Project not found: " + request.getProjectId()));
            entry.setProject(project);
        }
        if (request.getClockIn() != null) entry.setClockIn(request.getClockIn());
        if (request.getClockOut() != null) entry.setClockOut(request.getClockOut());
        if (request.getHourlyRate() != null) entry.setHourlyRate(request.getHourlyRate());
        if (request.getDescription() != null) entry.setDescription(request.getDescription());
        if (request.getTaskType() != null) entry.setTaskType(request.getTaskType());
        if (request.getBillable() != null) entry.setBillable(request.getBillable());

        if (entry.getClockIn() != null && entry.getClockOut() != null) {
            Duration duration = Duration.between(entry.getClockIn(), entry.getClockOut());
            BigDecimal hours = BigDecimal.valueOf(duration.toMinutes())
                    .divide(BigDecimal.valueOf(60), 2, RoundingMode.HALF_UP);
            entry.setHoursWorked(hours);
        }

        return timeEntryRepository.save(entry);
    }

    @Override
    @Transactional(readOnly = true)
    public TimeEntry getTimeEntryById(Long id) {
        return timeEntryRepository.findById(id)
                .orElseThrow(() -> new ResourceNotFoundException("Time entry not found: " + id));
    }

    @Override
    @Transactional(readOnly = true)
    public Page<TimeEntry> getTimeEntriesByProject(Long projectId, Pageable pageable) {
        return timeEntryRepository.findByProjectId(projectId, pageable);
    }

    @Override
    @Transactional(readOnly = true)
    public Page<TimeEntry> getTimeEntriesByUser(Long userId, Pageable pageable) {
        return timeEntryRepository.findByUserId(userId, pageable);
    }

    @Override
    @Transactional(readOnly = true)
    public List<TimeEntry> getTimeEntriesInRange(Long userId, Long projectId, LocalDateTime start, LocalDateTime end) {
        return timeEntryRepository.findEntriesInDateRange(start, end, userId, projectId);
    }

    @Override
    @Transactional
    public TimeEntry approveTimeEntry(Long id, Long approverId) {
        TimeEntry entry = getTimeEntryById(id);
        entry.setStatus(TimeEntry.TimeEntryStatus.APPROVED);
        entry.setApprovedBy(approverId);
        entry.setApprovedAt(LocalDateTime.now());
        return timeEntryRepository.save(entry);
    }

    @Override
    @Transactional
    public void deleteTimeEntry(Long id) {
        TimeEntry entry = getTimeEntryById(id);
        timeEntryRepository.delete(entry);
        log.info("Deleted time entry: {}", id);
    }

    @Override
    @Transactional(readOnly = true)
    public BigDecimal getTotalHoursForProject(Long projectId) {
        return timeEntryRepository.sumHoursWorkedByProject(projectId);
    }
}
