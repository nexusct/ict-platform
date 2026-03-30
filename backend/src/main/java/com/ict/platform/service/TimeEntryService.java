package com.ict.platform.service;

import com.ict.platform.dto.request.TimeEntryRequest;
import com.ict.platform.entity.TimeEntry;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;

import java.math.BigDecimal;
import java.time.LocalDateTime;
import java.util.List;

public interface TimeEntryService {

    TimeEntry clockIn(Long userId, Long projectId, BigDecimal latitude, BigDecimal longitude, String description);

    TimeEntry clockOut(Long userId, BigDecimal latitude, BigDecimal longitude);

    TimeEntry createTimeEntry(Long userId, TimeEntryRequest request);

    TimeEntry updateTimeEntry(Long id, TimeEntryRequest request);

    TimeEntry getTimeEntryById(Long id);

    Page<TimeEntry> getTimeEntriesByProject(Long projectId, Pageable pageable);

    Page<TimeEntry> getTimeEntriesByUser(Long userId, Pageable pageable);

    List<TimeEntry> getTimeEntriesInRange(Long userId, Long projectId, LocalDateTime start, LocalDateTime end);

    TimeEntry approveTimeEntry(Long id, Long approverId);

    void deleteTimeEntry(Long id);

    BigDecimal getTotalHoursForProject(Long projectId);
}
