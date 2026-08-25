package com.ict.platform.repository;

import com.ict.platform.entity.Project;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import org.springframework.stereotype.Repository;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;

@Repository
public interface ProjectRepository extends JpaRepository<Project, Long> {

    Optional<Project> findByProjectNumber(String projectNumber);

    boolean existsByProjectNumber(String projectNumber);

    Page<Project> findByStatus(Project.ProjectStatus status, Pageable pageable);

    Page<Project> findByAssignedTechnicianId(Long technicianId, Pageable pageable);

    Page<Project> findByProjectManagerId(Long managerId, Pageable pageable);

    @Query("SELECT p FROM Project p WHERE " +
           "(:status IS NULL OR p.status = :status) AND " +
           "(:priority IS NULL OR p.priority = :priority) AND " +
           "(:search IS NULL OR LOWER(p.name) LIKE LOWER(CONCAT('%', :search, '%')) " +
           "OR LOWER(p.clientName) LIKE LOWER(CONCAT('%', :search, '%')) " +
           "OR LOWER(p.projectNumber) LIKE LOWER(CONCAT('%', :search, '%')))")
    Page<Project> findWithFilters(
            @Param("status") Project.ProjectStatus status,
            @Param("priority") Project.Priority priority,
            @Param("search") String search,
            Pageable pageable);

    @Query("SELECT p FROM Project p WHERE p.endDate BETWEEN :startDate AND :endDate")
    List<Project> findProjectsDueInRange(
            @Param("startDate") LocalDate startDate,
            @Param("endDate") LocalDate endDate);

    @Query("SELECT COUNT(p) FROM Project p WHERE p.status = :status")
    long countByStatus(@Param("status") Project.ProjectStatus status);

    List<Project> findByZohoCrmDealId(String zohoCrmDealId);
}
