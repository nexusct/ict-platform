package com.ict.platform.controller;

import com.ict.platform.dto.response.ApiResponse;
import com.ict.platform.entity.PurchaseOrder;
import com.ict.platform.entity.User;
import com.ict.platform.service.PurchaseOrderService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import lombok.RequiredArgsConstructor;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.web.PageableDefault;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/purchase-orders")
@RequiredArgsConstructor
@Tag(name = "Purchase Orders", description = "Purchase order management endpoints")
public class PurchaseOrderController {

    private final PurchaseOrderService purchaseOrderService;

    @GetMapping
    @Operation(summary = "Get all purchase orders with optional filtering")
    public ResponseEntity<ApiResponse<Page<PurchaseOrder>>> getAllPurchaseOrders(
            @RequestParam(required = false) PurchaseOrder.POStatus status,
            @RequestParam(required = false) Long supplierId,
            @RequestParam(required = false) String search,
            @PageableDefault(size = 20, sort = "createdAt") Pageable pageable) {
        Page<PurchaseOrder> orders = purchaseOrderService.getAllPurchaseOrders(status, supplierId, search, pageable);
        return ResponseEntity.ok(ApiResponse.success(orders));
    }

    @GetMapping("/{id}")
    @Operation(summary = "Get a purchase order by ID")
    public ResponseEntity<ApiResponse<PurchaseOrder>> getPurchaseOrderById(@PathVariable Long id) {
        PurchaseOrder order = purchaseOrderService.getPurchaseOrderById(id);
        return ResponseEntity.ok(ApiResponse.success(order));
    }

    @PostMapping
    @Operation(summary = "Create a new purchase order")
    @PreAuthorize("hasAnyRole('ADMIN', 'INVENTORY_MANAGER')")
    public ResponseEntity<ApiResponse<PurchaseOrder>> createPurchaseOrder(@RequestBody PurchaseOrder order) {
        PurchaseOrder created = purchaseOrderService.createPurchaseOrder(order);
        return ResponseEntity.status(HttpStatus.CREATED)
                .body(ApiResponse.success("Purchase order created", created));
    }

    @PutMapping("/{id}")
    @Operation(summary = "Update a purchase order")
    @PreAuthorize("hasAnyRole('ADMIN', 'INVENTORY_MANAGER')")
    public ResponseEntity<ApiResponse<PurchaseOrder>> updatePurchaseOrder(
            @PathVariable Long id,
            @RequestBody PurchaseOrder updates) {
        PurchaseOrder order = purchaseOrderService.updatePurchaseOrder(id, updates);
        return ResponseEntity.ok(ApiResponse.success("Purchase order updated", order));
    }

    @DeleteMapping("/{id}")
    @Operation(summary = "Delete a purchase order")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<ApiResponse<Void>> deletePurchaseOrder(@PathVariable Long id) {
        purchaseOrderService.deletePurchaseOrder(id);
        return ResponseEntity.ok(ApiResponse.success("Purchase order deleted", null));
    }

    @PatchMapping("/{id}/status")
    @Operation(summary = "Update purchase order status (approve, receive, etc.)")
    @PreAuthorize("hasAnyRole('ADMIN', 'INVENTORY_MANAGER')")
    public ResponseEntity<ApiResponse<PurchaseOrder>> updateStatus(
            @PathVariable Long id,
            @RequestParam PurchaseOrder.POStatus status,
            @AuthenticationPrincipal User approver) {
        Long approverId = approver != null ? approver.getId() : null;
        PurchaseOrder order = purchaseOrderService.updateStatus(id, status, approverId);
        return ResponseEntity.ok(ApiResponse.success("Status updated", order));
    }

    @GetMapping("/project/{projectId}")
    @Operation(summary = "Get purchase orders for a project")
    public ResponseEntity<ApiResponse<List<PurchaseOrder>>> getByProject(@PathVariable Long projectId) {
        List<PurchaseOrder> orders = purchaseOrderService.getPurchaseOrdersByProject(projectId);
        return ResponseEntity.ok(ApiResponse.success(orders));
    }

    @PostMapping("/{id}/sync")
    @Operation(summary = "Sync purchase order to Zoho Books")
    @PreAuthorize("hasAnyRole('ADMIN', 'INVENTORY_MANAGER')")
    public ResponseEntity<ApiResponse<Void>> syncToZoho(@PathVariable Long id) {
        purchaseOrderService.syncToZoho(id);
        return ResponseEntity.ok(ApiResponse.success("Purchase order queued for sync", null));
    }
}
