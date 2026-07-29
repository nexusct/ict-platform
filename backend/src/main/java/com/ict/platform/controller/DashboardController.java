package com.ict.platform.controller;

import com.ict.platform.dto.response.ApiResponse;
import com.ict.platform.entity.Project;
import com.ict.platform.repository.InventoryItemRepository;
import com.ict.platform.repository.ProjectRepository;
import com.ict.platform.repository.SyncQueueRepository;
import com.ict.platform.repository.TimeEntryRepository;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import lombok.RequiredArgsConstructor;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.LinkedHashMap;
import java.util.Map;

@RestController
@RequestMapping("/dashboard")
@RequiredArgsConstructor
@Tag(name = "Dashboard", description = "Dashboard and summary endpoints")
public class DashboardController {

    private final ProjectRepository projectRepository;
    private final TimeEntryRepository timeEntryRepository;
    private final InventoryItemRepository inventoryItemRepository;
    private final SyncQueueRepository syncQueueRepository;

    @GetMapping("/summary")
    @Operation(summary = "Get dashboard summary statistics")
    public ResponseEntity<ApiResponse<Map<String, Object>>> getDashboardSummary() {
        Map<String, Object> summary = new LinkedHashMap<>();

        // Project stats
        Map<String, Object> projects = new LinkedHashMap<>();
        projects.put("total", projectRepository.count());
        projects.put("active", projectRepository.countByStatus(Project.ProjectStatus.ACTIVE));
        projects.put("pending", projectRepository.countByStatus(Project.ProjectStatus.PENDING));
        projects.put("completed", projectRepository.countByStatus(Project.ProjectStatus.COMPLETED));
        summary.put("projects", projects);

        // Inventory stats
        Map<String, Object> inventory = new LinkedHashMap<>();
        inventory.put("total", inventoryItemRepository.count());
        inventory.put("lowStock", inventoryItemRepository.findItemsBelowReorderPoint().size());
        inventory.put("outOfStock", inventoryItemRepository.findOutOfStockItems().size());
        summary.put("inventory", inventory);

        // Sync queue stats
        Map<String, Object> sync = new LinkedHashMap<>();
        sync.put("pending", syncQueueRepository.countByStatus(com.ict.platform.entity.SyncQueue.SyncStatus.PENDING));
        sync.put("failed", syncQueueRepository.countByStatus(com.ict.platform.entity.SyncQueue.SyncStatus.FAILED));
        summary.put("sync", sync);

        return ResponseEntity.ok(ApiResponse.success(summary));
    }
}
