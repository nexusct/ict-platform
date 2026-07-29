package com.ict.platform.service;

import com.ict.platform.entity.PurchaseOrder;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;

import java.util.List;
import java.util.Map;

public interface PurchaseOrderService {

    PurchaseOrder createPurchaseOrder(PurchaseOrder order);

    PurchaseOrder updatePurchaseOrder(Long id, PurchaseOrder order);

    PurchaseOrder getPurchaseOrderById(Long id);

    Page<PurchaseOrder> getAllPurchaseOrders(PurchaseOrder.POStatus status, Long supplierId, String search, Pageable pageable);

    void deletePurchaseOrder(Long id);

    PurchaseOrder updateStatus(Long id, PurchaseOrder.POStatus status, Long approverId);

    List<PurchaseOrder> getPurchaseOrdersByProject(Long projectId);

    void syncToZoho(Long id);
}
