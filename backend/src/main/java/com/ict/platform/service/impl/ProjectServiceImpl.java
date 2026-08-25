package com.ict.platform.service.impl;

import com.ict.platform.dto.request.ProjectRequest;
import com.ict.platform.entity.Project;
import com.ict.platform.entity.SyncQueue;
import com.ict.platform.exception.ResourceNotFoundException;
import com.ict.platform.repository.ProjectRepository;
import com.ict.platform.repository.SyncQueueRepository;
import com.ict.platform.service.ProjectService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;

@Service
@RequiredArgsConstructor
@Slf4j
public class ProjectServiceImpl implements ProjectService {

    private final ProjectRepository projectRepository;
    private final SyncQueueRepository syncQueueRepository;

    @Override
    @Transactional
    public Project createProject(ProjectRequest request) {
        String projectNumber = generateProjectNumber();

        Project project = Project.builder()
                .projectNumber(projectNumber)
                .name(request.getName())
                .description(request.getDescription())
                .status(request.getStatus() != null ? request.getStatus() : Project.ProjectStatus.PENDING)
                .priority(request.getPriority())
                .startDate(request.getStartDate())
                .endDate(request.getEndDate())
                .budget(request.getBudget())
                .clientName(request.getClientName())
                .clientEmail(request.getClientEmail())
                .clientPhone(request.getClientPhone())
                .siteAddress(request.getSiteAddress())
                .siteLatitude(request.getSiteLatitude())
                .siteLongitude(request.getSiteLongitude())
                .assignedTechnicianId(request.getAssignedTechnicianId())
                .projectManagerId(request.getProjectManagerId())
                .completionPercentage(request.getCompletionPercentage() != null ? request.getCompletionPercentage() : 0)
                .notes(request.getNotes())
                .build();

        project = projectRepository.save(project);
        log.info("Created project: {} ({})", project.getName(), project.getProjectNumber());

        queueSync(project.getId(), "project", SyncQueue.SyncAction.CREATE, SyncQueue.ZohoService.CRM);

        return project;
    }

    @Override
    @Transactional
    public Project updateProject(Long id, ProjectRequest request) {
        Project project = getProjectById(id);

        project.setName(request.getName());
        project.setDescription(request.getDescription());
        if (request.getStatus() != null) {
            project.setStatus(request.getStatus());
        }
        project.setPriority(request.getPriority());
        project.setStartDate(request.getStartDate());
        project.setEndDate(request.getEndDate());
        project.setBudget(request.getBudget());
        project.setClientName(request.getClientName());
        project.setClientEmail(request.getClientEmail());
        project.setClientPhone(request.getClientPhone());
        project.setSiteAddress(request.getSiteAddress());
        project.setSiteLatitude(request.getSiteLatitude());
        project.setSiteLongitude(request.getSiteLongitude());
        project.setAssignedTechnicianId(request.getAssignedTechnicianId());
        project.setProjectManagerId(request.getProjectManagerId());
        if (request.getCompletionPercentage() != null) {
            project.setCompletionPercentage(request.getCompletionPercentage());
        }
        project.setNotes(request.getNotes());

        project = projectRepository.save(project);
        log.info("Updated project: {} ({})", project.getName(), project.getProjectNumber());

        queueSync(project.getId(), "project", SyncQueue.SyncAction.UPDATE, SyncQueue.ZohoService.CRM);

        return project;
    }

    @Override
    @Transactional(readOnly = true)
    public Project getProjectById(Long id) {
        return projectRepository.findById(id)
                .orElseThrow(() -> new ResourceNotFoundException("Project not found with id: " + id));
    }

    @Override
    @Transactional(readOnly = true)
    public Page<Project> getAllProjects(Project.ProjectStatus status, Project.Priority priority, String search, Pageable pageable) {
        return projectRepository.findWithFilters(status, priority, search, pageable);
    }

    @Override
    @Transactional
    public void deleteProject(Long id) {
        Project project = getProjectById(id);
        projectRepository.delete(project);
        log.info("Deleted project: {}", id);

        queueSync(id, "project", SyncQueue.SyncAction.DELETE, SyncQueue.ZohoService.CRM);
    }

    @Override
    @Transactional
    public Project updateProjectStatus(Long id, Project.ProjectStatus status) {
        Project project = getProjectById(id);
        project.setStatus(status);
        if (status == Project.ProjectStatus.COMPLETED) {
            project.setActualEndDate(LocalDate.now());
            project.setCompletionPercentage(100);
        }
        project = projectRepository.save(project);
        log.info("Updated project {} status to {}", id, status);

        queueSync(id, "project", SyncQueue.SyncAction.UPDATE, SyncQueue.ZohoService.CRM);

        return project;
    }

    @Override
    @Transactional(readOnly = true)
    public List<Project> getProjectsDueSoon(int days) {
        LocalDate today = LocalDate.now();
        LocalDate dueDate = today.plusDays(days);
        return projectRepository.findProjectsDueInRange(today, dueDate);
    }

    @Override
    @Transactional
    public void syncProjectToZoho(Long id) {
        getProjectById(id);
        queueSync(id, "project", SyncQueue.SyncAction.UPDATE, SyncQueue.ZohoService.CRM);
        queueSync(id, "project", SyncQueue.SyncAction.UPDATE, SyncQueue.ZohoService.FSM);
        log.info("Queued sync for project: {}", id);
    }

    private String generateProjectNumber() {
        int year = LocalDate.now().getYear();
        String prefix = "PRJ-" + year + "-";
        long count = projectRepository.count() + 1;
        String number = prefix + String.format("%05d", count);
        // Ensure uniqueness in case of race conditions or gaps
        while (projectRepository.existsByProjectNumber(number)) {
            count++;
            number = prefix + String.format("%05d", count);
        }
        return number;
    }

    private void queueSync(Long entityId, String entityType, SyncQueue.SyncAction action, SyncQueue.ZohoService service) {
        SyncQueue syncItem = SyncQueue.builder()
                .entityType(entityType)
                .entityId(entityId)
                .action(action)
                .zohoService(service)
                .priority(5)
                .build();
        syncQueueRepository.save(syncItem);
    }
}
