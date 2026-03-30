package com.ict.platform.controller;

import com.ict.platform.dto.request.ProjectRequest;
import com.ict.platform.dto.response.ApiResponse;
import com.ict.platform.entity.Project;
import com.ict.platform.service.ProjectService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import lombok.RequiredArgsConstructor;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.web.PageableDefault;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/projects")
@RequiredArgsConstructor
@Tag(name = "Projects", description = "Project management endpoints")
public class ProjectController {

    private final ProjectService projectService;

    @GetMapping
    @Operation(summary = "Get all projects with optional filtering and pagination")
    public ResponseEntity<ApiResponse<Page<Project>>> getAllProjects(
            @RequestParam(required = false) Project.ProjectStatus status,
            @RequestParam(required = false) Project.Priority priority,
            @RequestParam(required = false) String search,
            @PageableDefault(size = 20, sort = "createdAt") Pageable pageable) {
        Page<Project> projects = projectService.getAllProjects(status, priority, search, pageable);
        return ResponseEntity.ok(ApiResponse.success(projects));
    }

    @GetMapping("/{id}")
    @Operation(summary = "Get a project by ID")
    public ResponseEntity<ApiResponse<Project>> getProjectById(@PathVariable Long id) {
        Project project = projectService.getProjectById(id);
        return ResponseEntity.ok(ApiResponse.success(project));
    }

    @PostMapping
    @Operation(summary = "Create a new project")
    @PreAuthorize("hasAnyRole('ADMIN', 'PROJECT_MANAGER')")
    public ResponseEntity<ApiResponse<Project>> createProject(@Valid @RequestBody ProjectRequest request) {
        Project project = projectService.createProject(request);
        return ResponseEntity.status(HttpStatus.CREATED)
                .body(ApiResponse.success("Project created successfully", project));
    }

    @PutMapping("/{id}")
    @Operation(summary = "Update a project")
    @PreAuthorize("hasAnyRole('ADMIN', 'PROJECT_MANAGER')")
    public ResponseEntity<ApiResponse<Project>> updateProject(
            @PathVariable Long id,
            @Valid @RequestBody ProjectRequest request) {
        Project project = projectService.updateProject(id, request);
        return ResponseEntity.ok(ApiResponse.success("Project updated successfully", project));
    }

    @PatchMapping("/{id}/status")
    @Operation(summary = "Update project status")
    @PreAuthorize("hasAnyRole('ADMIN', 'PROJECT_MANAGER')")
    public ResponseEntity<ApiResponse<Project>> updateProjectStatus(
            @PathVariable Long id,
            @RequestParam Project.ProjectStatus status) {
        Project project = projectService.updateProjectStatus(id, status);
        return ResponseEntity.ok(ApiResponse.success("Project status updated", project));
    }

    @DeleteMapping("/{id}")
    @Operation(summary = "Delete a project")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<ApiResponse<Void>> deleteProject(@PathVariable Long id) {
        projectService.deleteProject(id);
        return ResponseEntity.ok(ApiResponse.success("Project deleted successfully", null));
    }

    @GetMapping("/due-soon")
    @Operation(summary = "Get projects due in the next N days")
    public ResponseEntity<ApiResponse<List<Project>>> getProjectsDueSoon(
            @RequestParam(defaultValue = "7") int days) {
        List<Project> projects = projectService.getProjectsDueSoon(days);
        return ResponseEntity.ok(ApiResponse.success(projects));
    }

    @PostMapping("/{id}/sync")
    @Operation(summary = "Sync a project to Zoho")
    @PreAuthorize("hasAnyRole('ADMIN', 'PROJECT_MANAGER')")
    public ResponseEntity<ApiResponse<Void>> syncProject(@PathVariable Long id) {
        projectService.syncProjectToZoho(id);
        return ResponseEntity.ok(ApiResponse.success("Project queued for sync", null));
    }
}
