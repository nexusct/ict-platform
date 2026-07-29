package com.ict.platform.controller;

import com.ict.platform.dto.request.TimeEntryRequest;
import com.ict.platform.dto.response.ApiResponse;
import com.ict.platform.entity.TimeEntry;
import com.ict.platform.entity.User;
import com.ict.platform.service.TimeEntryService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import lombok.RequiredArgsConstructor;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.web.PageableDefault;
import org.springframework.format.annotation.DateTimeFormat;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.math.BigDecimal;
import java.time.LocalDateTime;
import java.util.List;

@RestController
@RequestMapping("/time-entries")
@RequiredArgsConstructor
@Tag(name = "Time Tracking", description = "Time entry and clock in/out endpoints")
public class TimeEntryController {

    private final TimeEntryService timeEntryService;

    @PostMapping("/clock-in")
    @Operation(summary = "Clock in to a project")
    public ResponseEntity<ApiResponse<TimeEntry>> clockIn(
            @AuthenticationPrincipal User user,
            @RequestParam Long projectId,
            @RequestParam(required = false) BigDecimal latitude,
            @RequestParam(required = false) BigDecimal longitude,
            @RequestParam(required = false) String description) {
        TimeEntry entry = timeEntryService.clockIn(user.getId(), projectId, latitude, longitude, description);
        return ResponseEntity.status(HttpStatus.CREATED)
                .body(ApiResponse.success("Clocked in successfully", entry));
    }

    @PostMapping("/clock-out")
    @Operation(summary = "Clock out from active time entry")
    public ResponseEntity<ApiResponse<TimeEntry>> clockOut(
            @AuthenticationPrincipal User user,
            @RequestParam(required = false) BigDecimal latitude,
            @RequestParam(required = false) BigDecimal longitude) {
        TimeEntry entry = timeEntryService.clockOut(user.getId(), latitude, longitude);
        return ResponseEntity.ok(ApiResponse.success("Clocked out successfully", entry));
    }

    @PostMapping
    @Operation(summary = "Manually create a time entry")
    public ResponseEntity<ApiResponse<TimeEntry>> createTimeEntry(
            @AuthenticationPrincipal User user,
            @Valid @RequestBody TimeEntryRequest request) {
        TimeEntry entry = timeEntryService.createTimeEntry(user.getId(), request);
        return ResponseEntity.status(HttpStatus.CREATED)
                .body(ApiResponse.success("Time entry created", entry));
    }

    @PutMapping("/{id}")
    @Operation(summary = "Update a time entry")
    public ResponseEntity<ApiResponse<TimeEntry>> updateTimeEntry(
            @PathVariable Long id,
            @Valid @RequestBody TimeEntryRequest request) {
        TimeEntry entry = timeEntryService.updateTimeEntry(id, request);
        return ResponseEntity.ok(ApiResponse.success("Time entry updated", entry));
    }

    @GetMapping("/{id}")
    @Operation(summary = "Get a time entry by ID")
    public ResponseEntity<ApiResponse<TimeEntry>> getTimeEntryById(@PathVariable Long id) {
        TimeEntry entry = timeEntryService.getTimeEntryById(id);
        return ResponseEntity.ok(ApiResponse.success(entry));
    }

    @GetMapping("/project/{projectId}")
    @Operation(summary = "Get time entries for a project")
    public ResponseEntity<ApiResponse<Page<TimeEntry>>> getByProject(
            @PathVariable Long projectId,
            @PageableDefault(size = 20) Pageable pageable) {
        Page<TimeEntry> entries = timeEntryService.getTimeEntriesByProject(projectId, pageable);
        return ResponseEntity.ok(ApiResponse.success(entries));
    }

    @GetMapping("/user/{userId}")
    @Operation(summary = "Get time entries for a user")
    public ResponseEntity<ApiResponse<Page<TimeEntry>>> getByUser(
            @PathVariable Long userId,
            @PageableDefault(size = 20) Pageable pageable) {
        Page<TimeEntry> entries = timeEntryService.getTimeEntriesByUser(userId, pageable);
        return ResponseEntity.ok(ApiResponse.success(entries));
    }

    @GetMapping("/range")
    @Operation(summary = "Get time entries in a date range")
    public ResponseEntity<ApiResponse<List<TimeEntry>>> getInRange(
            @RequestParam(required = false) Long userId,
            @RequestParam(required = false) Long projectId,
            @RequestParam @DateTimeFormat(iso = DateTimeFormat.ISO.DATE_TIME) LocalDateTime start,
            @RequestParam @DateTimeFormat(iso = DateTimeFormat.ISO.DATE_TIME) LocalDateTime end) {
        List<TimeEntry> entries = timeEntryService.getTimeEntriesInRange(userId, projectId, start, end);
        return ResponseEntity.ok(ApiResponse.success(entries));
    }

    @PatchMapping("/{id}/approve")
    @Operation(summary = "Approve a time entry")
    @PreAuthorize("hasAnyRole('ADMIN', 'PROJECT_MANAGER')")
    public ResponseEntity<ApiResponse<TimeEntry>> approveTimeEntry(
            @PathVariable Long id,
            @AuthenticationPrincipal User approver) {
        TimeEntry entry = timeEntryService.approveTimeEntry(id, approver.getId());
        return ResponseEntity.ok(ApiResponse.success("Time entry approved", entry));
    }

    @DeleteMapping("/{id}")
    @Operation(summary = "Delete a time entry")
    @PreAuthorize("hasAnyRole('ADMIN', 'PROJECT_MANAGER')")
    public ResponseEntity<ApiResponse<Void>> deleteTimeEntry(@PathVariable Long id) {
        timeEntryService.deleteTimeEntry(id);
        return ResponseEntity.ok(ApiResponse.success("Time entry deleted", null));
    }

    @GetMapping("/project/{projectId}/total-hours")
    @Operation(summary = "Get total hours worked on a project")
    public ResponseEntity<ApiResponse<BigDecimal>> getTotalHours(@PathVariable Long projectId) {
        BigDecimal hours = timeEntryService.getTotalHoursForProject(projectId);
        return ResponseEntity.ok(ApiResponse.success(hours));
    }
}
