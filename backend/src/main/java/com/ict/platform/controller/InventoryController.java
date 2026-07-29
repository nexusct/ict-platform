package com.ict.platform.controller;

import com.ict.platform.dto.request.InventoryItemRequest;
import com.ict.platform.dto.response.ApiResponse;
import com.ict.platform.entity.InventoryItem;
import com.ict.platform.service.InventoryService;
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
@RequestMapping("/inventory")
@RequiredArgsConstructor
@Tag(name = "Inventory", description = "Inventory management endpoints")
public class InventoryController {

    private final InventoryService inventoryService;

    @GetMapping
    @Operation(summary = "Get all inventory items with optional filtering")
    public ResponseEntity<ApiResponse<Page<InventoryItem>>> getAllItems(
            @RequestParam(required = false) String category,
            @RequestParam(required = false) InventoryItem.ItemStatus status,
            @RequestParam(required = false) String search,
            @PageableDefault(size = 20, sort = "name") Pageable pageable) {
        Page<InventoryItem> items = inventoryService.getAllItems(category, status, search, pageable);
        return ResponseEntity.ok(ApiResponse.success(items));
    }

    @GetMapping("/{id}")
    @Operation(summary = "Get an inventory item by ID")
    public ResponseEntity<ApiResponse<InventoryItem>> getItemById(@PathVariable Long id) {
        InventoryItem item = inventoryService.getItemById(id);
        return ResponseEntity.ok(ApiResponse.success(item));
    }

    @PostMapping
    @Operation(summary = "Create a new inventory item")
    @PreAuthorize("hasAnyRole('ADMIN', 'INVENTORY_MANAGER')")
    public ResponseEntity<ApiResponse<InventoryItem>> createItem(@Valid @RequestBody InventoryItemRequest request) {
        InventoryItem item = inventoryService.createItem(request);
        return ResponseEntity.status(HttpStatus.CREATED)
                .body(ApiResponse.success("Inventory item created", item));
    }

    @PutMapping("/{id}")
    @Operation(summary = "Update an inventory item")
    @PreAuthorize("hasAnyRole('ADMIN', 'INVENTORY_MANAGER')")
    public ResponseEntity<ApiResponse<InventoryItem>> updateItem(
            @PathVariable Long id,
            @Valid @RequestBody InventoryItemRequest request) {
        InventoryItem item = inventoryService.updateItem(id, request);
        return ResponseEntity.ok(ApiResponse.success("Inventory item updated", item));
    }

    @DeleteMapping("/{id}")
    @Operation(summary = "Delete an inventory item")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<ApiResponse<Void>> deleteItem(@PathVariable Long id) {
        inventoryService.deleteItem(id);
        return ResponseEntity.ok(ApiResponse.success("Inventory item deleted", null));
    }

    @PatchMapping("/{id}/stock")
    @Operation(summary = "Adjust stock level for an inventory item")
    @PreAuthorize("hasAnyRole('ADMIN', 'INVENTORY_MANAGER')")
    public ResponseEntity<ApiResponse<InventoryItem>> adjustStock(
            @PathVariable Long id,
            @RequestParam int quantityChange,
            @RequestParam(required = false, defaultValue = "Manual adjustment") String reason) {
        InventoryItem item = inventoryService.adjustStock(id, quantityChange, reason);
        return ResponseEntity.ok(ApiResponse.success("Stock adjusted successfully", item));
    }

    @GetMapping("/low-stock")
    @Operation(summary = "Get items below reorder point")
    public ResponseEntity<ApiResponse<List<InventoryItem>>> getLowStockItems() {
        List<InventoryItem> items = inventoryService.getLowStockItems();
        return ResponseEntity.ok(ApiResponse.success(items));
    }

    @GetMapping("/out-of-stock")
    @Operation(summary = "Get out-of-stock items")
    public ResponseEntity<ApiResponse<List<InventoryItem>>> getOutOfStockItems() {
        List<InventoryItem> items = inventoryService.getOutOfStockItems();
        return ResponseEntity.ok(ApiResponse.success(items));
    }

    @PostMapping("/{id}/sync")
    @Operation(summary = "Sync inventory item to Zoho Books")
    @PreAuthorize("hasAnyRole('ADMIN', 'INVENTORY_MANAGER')")
    public ResponseEntity<ApiResponse<Void>> syncItem(@PathVariable Long id) {
        inventoryService.syncInventoryToZoho(id);
        return ResponseEntity.ok(ApiResponse.success("Item queued for sync", null));
    }
}
