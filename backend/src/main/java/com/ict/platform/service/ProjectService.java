package com.ict.platform.service;

import com.ict.platform.dto.request.ProjectRequest;
import com.ict.platform.entity.Project;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;

import java.util.List;

public interface ProjectService {

    Project createProject(ProjectRequest request);

    Project updateProject(Long id, ProjectRequest request);

    Project getProjectById(Long id);

    Page<Project> getAllProjects(Project.ProjectStatus status, Project.Priority priority, String search, Pageable pageable);

    void deleteProject(Long id);

    Project updateProjectStatus(Long id, Project.ProjectStatus status);

    List<Project> getProjectsDueSoon(int days);

    void syncProjectToZoho(Long id);
}
