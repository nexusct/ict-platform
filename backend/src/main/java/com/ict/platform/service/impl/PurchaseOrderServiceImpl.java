package com.ict.platform.service.impl;

import com.ict.platform.entity.PurchaseOrder;
import com.ict.platform.entity.SyncQueue;
import com.ict.platform.exception.ResourceNotFoundException;
import com.ict.platform.repository.PurchaseOrderRepository;
import com.ict.platform.repository.SyncQueueRepository;
import com.ict.platform.service.PurchaseOrderService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDate;
import java.util.List;

@Service
@RequiredArgsConstructor
@Slf4j
public class PurchaseOrderServiceImpl implements PurchaseOrderService {

    private final PurchaseOrderRepository purchaseOrderRepository;
    private final SyncQueueRepository syncQueueRepository;

    @Override
    @Transactional
    public PurchaseOrder createPurchaseOrder(PurchaseOrder order) {
        if (order.getPoNumber() == null || order.getPoNumber().isBlank()) {
            order.setPoNumber(generatePoNumber());
        }
        if (order.getOrderDate() == null) {
            order.setOrderDate(LocalDate.now());
        }
        PurchaseOrder saved = purchaseOrderRepository.save(order);
        log.info("Created purchase order: {}", saved.getPoNumber());
        queueSync(saved.getId(), SyncQueue.SyncAction.CREATE);
        return saved;
    }

    @Override
    @Transactional
    public PurchaseOrder updatePurchaseOrder(Long id, PurchaseOrder updates) {
        PurchaseOrder order = getPurchaseOrderById(id);
        if (updates.getSupplierName() != null) order.setSupplierName(updates.getSupplierName());
        if (updates.getExpectedDeliveryDate() != null) order.setExpectedDeliveryDate(updates.getExpectedDeliveryDate());
        if (updates.getNotes() != null) order.setNotes(updates.getNotes());
        if (updates.getShippingAddress() != null) order.setShippingAddress(updates.getShippingAddress());
        if (updates.getPaymentTerms() != null) order.setPaymentTerms(updates.getPaymentTerms());

        PurchaseOrder saved = purchaseOrderRepository.save(order);
        log.info("Updated purchase order: {}", saved.getPoNumber());
        queueSync(saved.getId(), SyncQueue.SyncAction.UPDATE);
        return saved;
    }

    @Override
    @Transactional(readOnly = true)
    public PurchaseOrder getPurchaseOrderById(Long id) {
        return purchaseOrderRepository.findById(id)
                .orElseThrow(() -> new ResourceNotFoundException("Purchase order not found: " + id));
    }

    @Override
    @Transactional(readOnly = true)
    public Page<PurchaseOrder> getAllPurchaseOrders(PurchaseOrder.POStatus status, Long supplierId, String search, Pageable pageable) {
        return purchaseOrderRepository.findWithFilters(status, supplierId, search, pageable);
    }

    @Override
    @Transactional
    public void deletePurchaseOrder(Long id) {
        PurchaseOrder order = getPurchaseOrderById(id);
        purchaseOrderRepository.delete(order);
        log.info("Deleted purchase order: {}", id);
        queueSync(id, SyncQueue.SyncAction.DELETE);
    }

    @Override
    @Transactional
    public PurchaseOrder updateStatus(Long id, PurchaseOrder.POStatus status, Long approverId) {
        PurchaseOrder order = getPurchaseOrderById(id);
        order.setStatus(status);
        if (status == PurchaseOrder.POStatus.APPROVED && approverId != null) {
            order.setApprovedBy(approverId);
        }
        if (status == PurchaseOrder.POStatus.RECEIVED) {
            order.setActualDeliveryDate(LocalDate.now());
        }
        PurchaseOrder saved = purchaseOrderRepository.save(order);
        queueSync(saved.getId(), SyncQueue.SyncAction.UPDATE);
        return saved;
    }

    @Override
    @Transactional(readOnly = true)
    public List<PurchaseOrder> getPurchaseOrdersByProject(Long projectId) {
        return purchaseOrderRepository.findByProjectId(projectId);
    }

    @Override
    @Transactional
    public void syncToZoho(Long id) {
        getPurchaseOrderById(id);
        queueSync(id, SyncQueue.SyncAction.UPDATE);
        log.info("Queued sync for purchase order: {}", id);
    }

    private String generatePoNumber() {
        int year = LocalDate.now().getYear();
        String prefix = "PO-" + year + "-";
        long count = purchaseOrderRepository.count() + 1;
        String number = prefix + String.format("%05d", count);
        // Ensure uniqueness in case of race conditions or gaps
        while (purchaseOrderRepository.existsByPoNumber(number)) {
            count++;
            number = prefix + String.format("%05d", count);
        }
        return number;
    }

    private void queueSync(Long entityId, SyncQueue.SyncAction action) {
        SyncQueue syncItem = SyncQueue.builder()
                .entityType("purchase_order")
                .entityId(entityId)
                .action(action)
                .zohoService(SyncQueue.ZohoService.BOOKS)
                .priority(5)
                .build();
        syncQueueRepository.save(syncItem);
    }
}
