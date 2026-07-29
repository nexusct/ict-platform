package com.ict.platform.service;

import com.ict.platform.entity.Project;
import com.ict.platform.entity.TimeEntry;
import com.ict.platform.exception.BusinessException;
import com.ict.platform.exception.ResourceNotFoundException;
import com.ict.platform.repository.ProjectRepository;
import com.ict.platform.repository.TimeEntryRepository;
import com.ict.platform.service.impl.TimeEntryServiceImpl;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.extension.ExtendWith;
import org.mockito.InjectMocks;
import org.mockito.Mock;
import org.mockito.junit.jupiter.MockitoExtension;

import java.math.BigDecimal;
import java.time.LocalDateTime;
import java.util.Optional;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.eq;
import static org.mockito.Mockito.*;

@ExtendWith(MockitoExtension.class)
class TimeEntryServiceTest {

    @Mock
    private TimeEntryRepository timeEntryRepository;

    @Mock
    private ProjectRepository projectRepository;

    @InjectMocks
    private TimeEntryServiceImpl timeEntryService;

    private Project testProject;

    @BeforeEach
    void setUp() {
        testProject = Project.builder()
                .name("Test Project")
                .projectNumber("PRJ-2024-00001")
                .status(Project.ProjectStatus.ACTIVE)
                .build();
    }

    @Test
    void clockIn_whenNotAlreadyClockedIn_shouldCreateActiveEntry() {
        when(timeEntryRepository.findActiveEntryByUserId(1L)).thenReturn(Optional.empty());
        when(projectRepository.findById(1L)).thenReturn(Optional.of(testProject));
        when(timeEntryRepository.save(any(TimeEntry.class))).thenAnswer(i -> i.getArguments()[0]);

        TimeEntry entry = timeEntryService.clockIn(1L, 1L, null, null, "Testing");

        assertThat(entry).isNotNull();
        assertThat(entry.getStatus()).isEqualTo(TimeEntry.TimeEntryStatus.ACTIVE);
        assertThat(entry.getClockIn()).isNotNull();
        assertThat(entry.getClockOut()).isNull();
    }

    @Test
    void clockIn_whenAlreadyClockedIn_shouldThrowException() {
        TimeEntry existingEntry = TimeEntry.builder()
                .status(TimeEntry.TimeEntryStatus.ACTIVE)
                .build();
        when(timeEntryRepository.findActiveEntryByUserId(1L)).thenReturn(Optional.of(existingEntry));

        assertThatThrownBy(() -> timeEntryService.clockIn(1L, 1L, null, null, null))
                .isInstanceOf(BusinessException.class)
                .hasMessageContaining("already clocked in");
    }

    @Test
    void clockOut_whenClockedIn_shouldCalculateHours() {
        LocalDateTime clockInTime = LocalDateTime.now().minusHours(4);
        TimeEntry activeEntry = TimeEntry.builder()
                .userId(1L)
                .project(testProject)
                .clockIn(clockInTime)
                .status(TimeEntry.TimeEntryStatus.ACTIVE)
                .overtimeHours(BigDecimal.ZERO)
                .billable(true)
                .build();

        when(timeEntryRepository.findActiveEntryByUserId(1L)).thenReturn(Optional.of(activeEntry));
        when(timeEntryRepository.save(any(TimeEntry.class))).thenAnswer(i -> i.getArguments()[0]);

        TimeEntry result = timeEntryService.clockOut(1L, null, null);

        assertThat(result.getStatus()).isEqualTo(TimeEntry.TimeEntryStatus.COMPLETED);
        assertThat(result.getHoursWorked()).isNotNull();
        assertThat(result.getHoursWorked()).isGreaterThan(BigDecimal.ZERO);
        assertThat(result.getOvertimeHours()).isEqualTo(BigDecimal.ZERO);
    }

    @Test
    void clockOut_whenNotClockedIn_shouldThrowException() {
        when(timeEntryRepository.findActiveEntryByUserId(1L)).thenReturn(Optional.empty());

        assertThatThrownBy(() -> timeEntryService.clockOut(1L, null, null))
                .isInstanceOf(BusinessException.class)
                .hasMessageContaining("not clocked in");
    }

    @Test
    void clockOut_withMoreThan8Hours_shouldCalculateOvertime() {
        LocalDateTime clockInTime = LocalDateTime.now().minusHours(10);
        TimeEntry activeEntry = TimeEntry.builder()
                .userId(1L)
                .project(testProject)
                .clockIn(clockInTime)
                .status(TimeEntry.TimeEntryStatus.ACTIVE)
                .overtimeHours(BigDecimal.ZERO)
                .billable(true)
                .build();

        when(timeEntryRepository.findActiveEntryByUserId(1L)).thenReturn(Optional.of(activeEntry));
        when(timeEntryRepository.save(any(TimeEntry.class))).thenAnswer(i -> i.getArguments()[0]);

        TimeEntry result = timeEntryService.clockOut(1L, null, null);

        assertThat(result.getOvertimeHours()).isGreaterThan(BigDecimal.ZERO);
    }

    @Test
    void getTimeEntryById_whenNotExists_shouldThrowException() {
        when(timeEntryRepository.findById(999L)).thenReturn(Optional.empty());

        assertThatThrownBy(() -> timeEntryService.getTimeEntryById(999L))
                .isInstanceOf(ResourceNotFoundException.class);
    }
}
