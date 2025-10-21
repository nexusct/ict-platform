/**
 * Purchase Order Form Component
 *
 * Comprehensive form for creating and editing purchase orders with:
 * - Auto PO number generation
 * - Multiple line items management
 * - Supplier selection
 * - Project association
 * - Tax and shipping calculations
 * - Draft and submit actions
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  createPurchaseOrder,
  updatePurchaseOrder,
  generatePONumber,
  selectCurrentPO,
  selectPOLoading,
  selectPOError,
  clearError,
} from '../../store/slices/purchaseOrdersSlice';
import {
  fetchInventory,
  selectInventory,
} from '../../store/slices/inventorySlice';
import type { PurchaseOrder, PurchaseOrderStatus, InventoryItem } from '../../types';

interface LineItem {
  id?: number;
  inventory_id: number;
  item_name?: string;
  description: string;
  quantity: number;
  unit_price: number;
  tax_rate: number;
  line_total: number;
}

interface POFormData {
  po_number: string;
  supplier_id: number;
  project_id?: number;
  po_date: string;
  delivery_date: string;
  status: PurchaseOrderStatus;
  notes: string;
  line_items: LineItem[];
  shipping_cost: number;
}

interface PurchaseOrderFormProps {
  purchaseOrder?: PurchaseOrder;
  onSuccess?: () => void;
  onCancel?: () => void;
}

const PurchaseOrderForm: React.FC<PurchaseOrderFormProps> = ({
  purchaseOrder,
  onSuccess,
  onCancel,
}) => {
  const dispatch = useDispatch();
  const currentPO = useSelector(selectCurrentPO);
  const loading = useSelector(selectPOLoading);
  const error = useSelector(selectPOError);
  const inventory = useSelector(selectInventory);

  const [formData, setFormData] = useState<POFormData>({
    po_number: '',
    supplier_id: 0,
    project_id: undefined,
    po_date: new Date().toISOString().split('T')[0],
    delivery_date: '',
    status: 'draft',
    notes: '',
    line_items: [],
    shipping_cost: 0,
  });

  const [showItemSelector, setShowItemSelector] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [validationError, setValidationError] = useState('');
  const [generatingPO, setGeneratingPO] = useState(false);

  // Load inventory on mount
  useEffect(() => {
    if (inventory.length === 0) {
      dispatch(fetchInventory({ per_page: 100 }) as any);
    }
  }, [dispatch, inventory.length]);

  // Load existing PO data if editing
  useEffect(() => {
    if (purchaseOrder) {
      setFormData({
        po_number: purchaseOrder.po_number,
        supplier_id: purchaseOrder.supplier_id,
        project_id: purchaseOrder.project_id,
        po_date: purchaseOrder.po_date.split('T')[0],
        delivery_date: purchaseOrder.delivery_date?.split('T')[0] || '',
        status: purchaseOrder.status,
        notes: purchaseOrder.notes || '',
        line_items: [], // Would need to load line items from API
        shipping_cost: purchaseOrder.shipping_cost,
      });
    }
  }, [purchaseOrder]);

  // Auto-generate PO number for new POs
  useEffect(() => {
    if (!purchaseOrder && !formData.po_number) {
      handleGeneratePONumber();
    }
  }, []);

  const handleGeneratePONumber = async () => {
    setGeneratingPO(true);
    try {
      const result = await dispatch(generatePONumber() as any).unwrap();
      setFormData({ ...formData, po_number: result });
    } catch (err) {
      console.error('Failed to generate PO number:', err);
    } finally {
      setGeneratingPO(false);
    }
  };

  const addLineItem = (item: InventoryItem) => {
    const newLineItem: LineItem = {
      inventory_id: item.id,
      item_name: item.item_name,
      description: item.description || item.item_name,
      quantity: 1,
      unit_price: item.unit_cost,
      tax_rate: 0,
      line_total: item.unit_cost,
    };

    setFormData({
      ...formData,
      line_items: [...formData.line_items, newLineItem],
    });

    setShowItemSelector(false);
    setSearchQuery('');
  };

  const updateLineItem = (index: number, field: keyof LineItem, value: any) => {
    const updatedItems = [...formData.line_items];
    updatedItems[index] = {
      ...updatedItems[index],
      [field]: value,
    };

    // Recalculate line total
    if (field === 'quantity' || field === 'unit_price' || field === 'tax_rate') {
      const item = updatedItems[index];
      const subtotal = item.quantity * item.unit_price;
      item.line_total = subtotal + (subtotal * item.tax_rate / 100);
    }

    setFormData({ ...formData, line_items: updatedItems });
  };

  const removeLineItem = (index: number) => {
    const updatedItems = formData.line_items.filter((_, i) => i !== index);
    setFormData({ ...formData, line_items: updatedItems });
  };

  const calculateSubtotal = (): number => {
    return formData.line_items.reduce((sum, item) => {
      return sum + (item.quantity * item.unit_price);
    }, 0);
  };

  const calculateTaxAmount = (): number => {
    return formData.line_items.reduce((sum, item) => {
      const subtotal = item.quantity * item.unit_price;
      return sum + (subtotal * item.tax_rate / 100);
    }, 0);
  };

  const calculateTotal = (): number => {
    return calculateSubtotal() + calculateTaxAmount() + formData.shipping_cost;
  };

  const validateForm = (): boolean => {
    if (!formData.po_number.trim()) {
      setValidationError('PO number is required');
      return false;
    }

    if (!formData.supplier_id) {
      setValidationError('Please select a supplier');
      return false;
    }

    if (!formData.po_date) {
      setValidationError('PO date is required');
      return false;
    }

    if (formData.line_items.length === 0) {
      setValidationError('Please add at least one line item');
      return false;
    }

    if (formData.line_items.some(item => item.quantity <= 0)) {
      setValidationError('All line items must have quantity greater than zero');
      return false;
    }

    if (formData.line_items.some(item => item.unit_price < 0)) {
      setValidationError('Unit prices cannot be negative');
      return false;
    }

    return true;
  };

  const handleSubmit = async (e: React.FormEvent, submitStatus: PurchaseOrderStatus = 'pending') => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    try {
      const poData = {
        po_number: formData.po_number,
        supplier_id: formData.supplier_id,
        project_id: formData.project_id,
        po_date: formData.po_date,
        delivery_date: formData.delivery_date || undefined,
        status: submitStatus,
        notes: formData.notes,
        line_items: formData.line_items.map(item => ({
          id: item.id,
          inventory_id: item.inventory_id,
          description: item.description,
          quantity: item.quantity,
          unit_price: item.unit_price,
          tax_rate: item.tax_rate,
        })),
      };

      if (purchaseOrder) {
        await dispatch(updatePurchaseOrder({ id: purchaseOrder.id, data: poData }) as any).unwrap();
      } else {
        await dispatch(createPurchaseOrder(poData as any) as any).unwrap();
      }

      if (onSuccess) {
        onSuccess();
      }
    } catch (err) {
      setValidationError('Failed to save purchase order. Please try again.');
    }
  };

  const filteredInventory = inventory.filter((item: InventoryItem) =>
    item.item_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    item.sku.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const subtotal = calculateSubtotal();
  const taxAmount = calculateTaxAmount();
  const total = calculateTotal();

  return (
    <div className="purchase-order-form">
      <div className="po-form__header">
        <h2>{purchaseOrder ? 'Edit Purchase Order' : 'Create Purchase Order'}</h2>
        {formData.po_number && (
          <div className="po-number-badge">{formData.po_number}</div>
        )}
      </div>

      {error && (
        <div className="alert alert--error">
          <span className="alert__icon">❌</span>
          <span>{error}</span>
          <button onClick={() => dispatch(clearError())} className="alert__close">×</button>
        </div>
      )}

      {validationError && (
        <div className="alert alert--error">
          <span className="alert__icon">⚠️</span>
          <span>{validationError}</span>
          <button onClick={() => setValidationError('')} className="alert__close">×</button>
        </div>
      )}

      <form onSubmit={(e) => handleSubmit(e)} className="po-form">
        {/* Header Information */}
        <div className="po-form__section">
          <h3>Order Information</h3>
          <div className="form-row">
            <div className="form-group">
              <label className="form-label">PO Number *</label>
              <div className="po-number-input-group">
                <input
                  type="text"
                  value={formData.po_number}
                  onChange={(e) => setFormData({ ...formData, po_number: e.target.value })}
                  className="form-input"
                  required
                  disabled={generatingPO}
                />
                <button
                  type="button"
                  onClick={handleGeneratePONumber}
                  disabled={generatingPO || !!purchaseOrder}
                  className="btn btn-secondary btn-sm"
                >
                  {generatingPO ? '...' : 'Generate'}
                </button>
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Supplier ID *</label>
              <input
                type="number"
                value={formData.supplier_id || ''}
                onChange={(e) => setFormData({ ...formData, supplier_id: parseInt(e.target.value) || 0 })}
                className="form-input"
                required
              />
            </div>

            <div className="form-group">
              <label className="form-label">Project ID (Optional)</label>
              <input
                type="number"
                value={formData.project_id || ''}
                onChange={(e) => setFormData({ ...formData, project_id: parseInt(e.target.value) || undefined })}
                className="form-input"
              />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label className="form-label">PO Date *</label>
              <input
                type="date"
                value={formData.po_date}
                onChange={(e) => setFormData({ ...formData, po_date: e.target.value })}
                className="form-input"
                required
              />
            </div>

            <div className="form-group">
              <label className="form-label">Delivery Date</label>
              <input
                type="date"
                value={formData.delivery_date}
                onChange={(e) => setFormData({ ...formData, delivery_date: e.target.value })}
                className="form-input"
              />
            </div>

            <div className="form-group">
              <label className="form-label">Status</label>
              <select
                value={formData.status}
                onChange={(e) => setFormData({ ...formData, status: e.target.value as PurchaseOrderStatus })}
                className="form-select"
              >
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="ordered">Ordered</option>
              </select>
            </div>
          </div>
        </div>

        {/* Line Items */}
        <div className="po-form__section">
          <div className="section-header">
            <h3>Line Items</h3>
            <button
              type="button"
              onClick={() => setShowItemSelector(!showItemSelector)}
              className="btn btn-primary btn-sm"
            >
              + Add Item
            </button>
          </div>

          {showItemSelector && (
            <div className="item-selector-panel">
              <input
                type="text"
                placeholder="Search inventory items..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="form-input"
                autoFocus
              />
              <div className="item-results">
                {filteredInventory.slice(0, 10).map((item: InventoryItem) => (
                  <div
                    key={item.id}
                    onClick={() => addLineItem(item)}
                    className="item-result"
                  >
                    <div className="item-result__info">
                      <h4>{item.item_name}</h4>
                      <p>{item.sku}</p>
                    </div>
                    <div className="item-result__price">
                      ${item.unit_cost.toFixed(2)}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {formData.line_items.length === 0 ? (
            <div className="empty-state">
              <p>No line items added yet</p>
            </div>
          ) : (
            <div className="line-items-table">
              <table>
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Tax %</th>
                    <th>Total</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {formData.line_items.map((item, index) => (
                    <tr key={index}>
                      <td>
                        <div className="line-item-name">{item.item_name || `Item ${item.inventory_id}`}</div>
                      </td>
                      <td>
                        <input
                          type="text"
                          value={item.description}
                          onChange={(e) => updateLineItem(index, 'description', e.target.value)}
                          className="form-input form-input--sm"
                        />
                      </td>
                      <td>
                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          value={item.quantity}
                          onChange={(e) => updateLineItem(index, 'quantity', parseFloat(e.target.value) || 0)}
                          className="form-input form-input--sm form-input--number"
                        />
                      </td>
                      <td>
                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          value={item.unit_price}
                          onChange={(e) => updateLineItem(index, 'unit_price', parseFloat(e.target.value) || 0)}
                          className="form-input form-input--sm form-input--number"
                        />
                      </td>
                      <td>
                        <input
                          type="number"
                          min="0"
                          max="100"
                          step="0.1"
                          value={item.tax_rate}
                          onChange={(e) => updateLineItem(index, 'tax_rate', parseFloat(e.target.value) || 0)}
                          className="form-input form-input--sm form-input--number"
                        />
                      </td>
                      <td>
                        <div className="line-total">${item.line_total.toFixed(2)}</div>
                      </td>
                      <td>
                        <button
                          type="button"
                          onClick={() => removeLineItem(index)}
                          className="btn-icon btn-icon--danger"
                        >
                          🗑️
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Totals */}
        {formData.line_items.length > 0 && (
          <div className="po-form__section po-form__totals">
            <div className="totals-grid">
              <div className="totals-row">
                <span className="label">Subtotal:</span>
                <span className="value">${subtotal.toFixed(2)}</span>
              </div>
              <div className="totals-row">
                <span className="label">Tax:</span>
                <span className="value">${taxAmount.toFixed(2)}</span>
              </div>
              <div className="totals-row">
                <span className="label">Shipping:</span>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={formData.shipping_cost}
                  onChange={(e) => setFormData({ ...formData, shipping_cost: parseFloat(e.target.value) || 0 })}
                  className="form-input form-input--sm form-input--number"
                />
              </div>
              <div className="totals-row totals-row--total">
                <span className="label">Total:</span>
                <span className="value">${total.toFixed(2)}</span>
              </div>
            </div>
          </div>
        )}

        {/* Notes */}
        <div className="po-form__section">
          <div className="form-group">
            <label className="form-label">Notes</label>
            <textarea
              value={formData.notes}
              onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
              rows={4}
              className="form-textarea"
              placeholder="Add any additional notes or instructions..."
            />
          </div>
        </div>

        {/* Form Actions */}
        <div className="po-form__actions">
          <button
            type="button"
            onClick={onCancel}
            className="btn btn-secondary"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={(e) => handleSubmit(e, 'draft')}
            disabled={loading}
            className="btn btn-secondary"
          >
            Save as Draft
          </button>
          <button
            type="submit"
            disabled={loading}
            className="btn btn-primary"
          >
            {loading ? 'Saving...' : purchaseOrder ? 'Update PO' : 'Create PO'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default PurchaseOrderForm;
