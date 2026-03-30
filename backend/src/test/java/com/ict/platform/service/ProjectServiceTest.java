package com.ict.platform.service;

import com.ict.platform.dto.request.ProjectRequest;
import com.ict.platform.entity.Project;
import com.ict.platform.entity.SyncQueue;
import com.ict.platform.exception.ResourceNotFoundException;
import com.ict.platform.repository.ProjectRepository;
import com.ict.platform.repository.SyncQueueRepository;
import com.ict.platform.service.impl.ProjectServiceImpl;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.extension.ExtendWith;
import org.mockito.InjectMocks;
import org.mockito.Mock;
import org.mockito.junit.jupiter.MockitoExtension;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageImpl;
import org.springframework.data.domain.PageRequest;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.*;

@ExtendWith(MockitoExtension.class)
class ProjectServiceTest {

    @Mock
    private ProjectRepository projectRepository;

    @Mock
    private SyncQueueRepository syncQueueRepository;

    @InjectMocks
    private ProjectServiceImpl projectService;

    private Project testProject;
    private ProjectRequest testRequest;

    @BeforeEach
    void setUp() {
        testProject = Project.builder()
                .name("Test Project")
                .projectNumber("PRJ-2024-00001")
                .status(Project.ProjectStatus.ACTIVE)
                .priority(Project.Priority.HIGH)
                .clientName("ACME Corp")
                .budget(new BigDecimal("50000.00"))
                .startDate(LocalDate.now())
                .endDate(LocalDate.now().plusMonths(3))
                .build();
        testProject = setId(testProject, 1L);

        testRequest = new ProjectRequest();
        testRequest.setName("Test Project");
        testRequest.setStatus(Project.ProjectStatus.ACTIVE);
        testRequest.setPriority(Project.Priority.HIGH);
        testRequest.setClientName("ACME Corp");
        testRequest.setBudget(new BigDecimal("50000.00"));
        testRequest.setStartDate(LocalDate.now());
        testRequest.setEndDate(LocalDate.now().plusMonths(3));
    }

    @Test
    void createProject_shouldCreateAndReturnProject() {
        when(projectRepository.existsByProjectNumber(anyString())).thenReturn(false);
        when(projectRepository.save(any(Project.class))).thenReturn(testProject);
        when(syncQueueRepository.save(any(SyncQueue.class))).thenAnswer(i -> i.getArguments()[0]);

        Project result = projectService.createProject(testRequest);

        assertThat(result).isNotNull();
        assertThat(result.getName()).isEqualTo("Test Project");
        verify(projectRepository, times(1)).save(any(Project.class));
        verify(syncQueueRepository, times(1)).save(any(SyncQueue.class));
    }

    @Test
    void getProjectById_whenExists_shouldReturnProject() {
        when(projectRepository.findById(1L)).thenReturn(Optional.of(testProject));

        Project result = projectService.getProjectById(1L);

        assertThat(result).isNotNull();
        assertThat(result.getName()).isEqualTo("Test Project");
    }

    @Test
    void getProjectById_whenNotExists_shouldThrowException() {
        when(projectRepository.findById(999L)).thenReturn(Optional.empty());

        assertThatThrownBy(() -> projectService.getProjectById(999L))
                .isInstanceOf(ResourceNotFoundException.class)
                .hasMessageContaining("999");
    }

    @Test
    void getAllProjects_shouldReturnPagedResults() {
        Page<Project> page = new PageImpl<>(List.of(testProject));
        when(projectRepository.findWithFilters(any(), any(), any(), any())).thenReturn(page);

        Page<Project> result = projectService.getAllProjects(null, null, null, PageRequest.of(0, 20));

        assertThat(result).isNotNull();
        assertThat(result.getContent()).hasSize(1);
    }

    @Test
    void updateProjectStatus_toCompleted_shouldSetActualEndDate() {
        when(projectRepository.findById(1L)).thenReturn(Optional.of(testProject));
        when(projectRepository.save(any(Project.class))).thenAnswer(i -> i.getArguments()[0]);
        when(syncQueueRepository.save(any(SyncQueue.class))).thenAnswer(i -> i.getArguments()[0]);

        Project result = projectService.updateProjectStatus(1L, Project.ProjectStatus.COMPLETED);

        assertThat(result.getStatus()).isEqualTo(Project.ProjectStatus.COMPLETED);
        assertThat(result.getActualEndDate()).isNotNull();
        assertThat(result.getCompletionPercentage()).isEqualTo(100);
    }

    @Test
    void deleteProject_shouldDeleteAndQueueSync() {
        when(projectRepository.findById(1L)).thenReturn(Optional.of(testProject));
        when(syncQueueRepository.save(any(SyncQueue.class))).thenAnswer(i -> i.getArguments()[0]);

        projectService.deleteProject(1L);

        verify(projectRepository, times(1)).delete(testProject);
        verify(syncQueueRepository, times(1)).save(any(SyncQueue.class));
    }

    private Project setId(Project project, Long id) {
        try {
            java.lang.reflect.Field field = com.ict.platform.entity.BaseEntity.class.getDeclaredField("id");
            field.setAccessible(true);
            field.set(project, id);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
        return project;
    }
}
