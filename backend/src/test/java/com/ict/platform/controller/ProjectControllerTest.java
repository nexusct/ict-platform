package com.ict.platform.controller;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.ict.platform.dto.request.ProjectRequest;
import com.ict.platform.entity.Project;
import com.ict.platform.security.jwt.JwtService;
import com.ict.platform.service.ProjectService;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.WebMvcTest;
import org.springframework.boot.test.mock.mockito.MockBean;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageImpl;
import org.springframework.http.MediaType;
import org.springframework.security.authentication.AuthenticationProvider;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.test.context.support.WithMockUser;
import org.springframework.test.web.servlet.MockMvc;

import java.time.LocalDate;
import java.util.List;

import static org.mockito.ArgumentMatchers.any;
import static org.mockito.Mockito.when;
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

@WebMvcTest(ProjectController.class)
class ProjectControllerTest {

    @Autowired
    private MockMvc mockMvc;

    @Autowired
    private ObjectMapper objectMapper;

    @MockBean
    private ProjectService projectService;

    @MockBean
    private JwtService jwtService;

    @MockBean
    private UserDetailsService userDetailsService;

    @MockBean
    private AuthenticationProvider authenticationProvider;

    private Project testProject;

    @BeforeEach
    void setUp() {
        testProject = Project.builder()
                .name("Test Project")
                .projectNumber("PRJ-2024-00001")
                .status(Project.ProjectStatus.ACTIVE)
                .priority(Project.Priority.HIGH)
                .clientName("ACME Corp")
                .startDate(LocalDate.now())
                .endDate(LocalDate.now().plusMonths(3))
                .build();
    }

    @Test
    @WithMockUser(roles = "PROJECT_MANAGER")
    void getAllProjects_shouldReturnPagedResults() throws Exception {
        Page<Project> page = new PageImpl<>(List.of(testProject));
        when(projectService.getAllProjects(any(), any(), any(), any())).thenReturn(page);

        mockMvc.perform(get("/projects")
                .contentType(MediaType.APPLICATION_JSON))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.content").isArray());
    }

    @Test
    @WithMockUser(roles = "PROJECT_MANAGER")
    void getProjectById_shouldReturnProject() throws Exception {
        when(projectService.getProjectById(1L)).thenReturn(testProject);

        mockMvc.perform(get("/projects/1")
                .contentType(MediaType.APPLICATION_JSON))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.name").value("Test Project"));
    }

    @Test
    @WithMockUser(roles = "ADMIN")
    void createProject_withValidData_shouldReturnCreated() throws Exception {
        ProjectRequest request = new ProjectRequest();
        request.setName("New Project");
        request.setStatus(Project.ProjectStatus.PENDING);
        request.setStartDate(LocalDate.now());

        when(projectService.createProject(any(ProjectRequest.class))).thenReturn(testProject);

        mockMvc.perform(post("/projects")
                .with(csrf())
                .contentType(MediaType.APPLICATION_JSON)
                .content(objectMapper.writeValueAsString(request)))
                .andExpect(status().isCreated())
                .andExpect(jsonPath("$.success").value(true));
    }

    @Test
    void createProject_withoutAuthentication_shouldReturnUnauthorized() throws Exception {
        ProjectRequest request = new ProjectRequest();
        request.setName("New Project");
        request.setStatus(Project.ProjectStatus.PENDING);

        mockMvc.perform(post("/projects")
                .with(csrf())
                .contentType(MediaType.APPLICATION_JSON)
                .content(objectMapper.writeValueAsString(request)))
                .andExpect(status().isUnauthorized());
    }
}
