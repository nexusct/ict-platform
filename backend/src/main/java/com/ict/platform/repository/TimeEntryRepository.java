package com.ict.platform.repository;

import com.ict.platform.entity.TimeEntry;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import org.springframework.stereotype.Repository;

import java.math.BigDecimal;
import java.time.LocalDateTime;
import java.util.List;
import java.util.Optional;

@Repository
public interface TimeEntryRepository extends JpaRepository<TimeEntry, Long> {

    Page<TimeEntry> findByProjectId(Long projectId, Pageable pageable);

    Page<TimeEntry> findByUserId(Long userId, Pageable pageable);

    Optional<TimeEntry> findByUserIdAndStatus(Long userId, TimeEntry.TimeEntryStatus status);

    @Query("SELECT t FROM TimeEntry t WHERE t.userId = :userId AND t.status = 'ACTIVE'")
    Optional<TimeEntry> findActiveEntryByUserId(@Param("userId") Long userId);

    @Query("SELECT t FROM TimeEntry t WHERE " +
           "t.clockIn >= :startDate AND t.clockIn <= :endDate AND " +
           "(:userId IS NULL OR t.userId = :userId) AND " +
           "(:projectId IS NULL OR t.project.id = :projectId)")
    List<TimeEntry> findEntriesInDateRange(
            @Param("startDate") LocalDateTime startDate,
            @Param("endDate") LocalDateTime endDate,
            @Param("userId") Long userId,
            @Param("projectId") Long projectId);

    @Query("SELECT COALESCE(SUM(t.hoursWorked), 0) FROM TimeEntry t WHERE t.project.id = :projectId")
    BigDecimal sumHoursWorkedByProject(@Param("projectId") Long projectId);

    @Query("SELECT COALESCE(SUM(t.hoursWorked), 0) FROM TimeEntry t WHERE t.userId = :userId AND " +
           "t.clockIn >= :startDate AND t.clockIn <= :endDate")
    BigDecimal sumHoursWorkedByUserInPeriod(
            @Param("userId") Long userId,
            @Param("startDate") LocalDateTime startDate,
            @Param("endDate") LocalDateTime endDate);

    Page<TimeEntry> findByStatus(TimeEntry.TimeEntryStatus status, Pageable pageable);
}
