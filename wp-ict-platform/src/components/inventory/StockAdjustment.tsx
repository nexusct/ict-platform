/**
 * Stock Adjustment Component
 *
 * Allows users to adjust inventory stock levels with:
 * - Multiple adjustment types (received, consumed, damaged, etc.)
 * - Quantity input with validation
 * - Reason and reference tracking
 * - Project association
 * - Real-time stock preview
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchInventory,
  adjustStock,
  fetchStockHistory,
  selectInventory,
  selectInventoryLoading,
  selectInventoryError,
  clearError,
} from '../../store/slices/inventorySlice';
import type { InventoryItem } from '../../types';

interface StockAdjustmentFormData {
  item_id: number;
  adjustment: number;
  adjustment_type: 'received' | 'consumed' | 'damaged' | 'lost' | 'returned' | 'transfer' | 'correction' | 'initial';
  reason: string;
  project_id?: number;
  reference?: string;
}

interface AdjustmentTypeOption {
  value: StockAdjustmentFormData['adjustment_type'];
  label: string;
  description: string;
  icon: string;
  color: string;
  allowNegative: boolean;
}

const StockAdjustment: React.FC = () => {
  const dispatch = useDispatch();
  const inventory = useSelector(selectInventory);
  const loading = useSelector(selectInventoryLoading);
  const error = useSelector(selectInventoryError);

  const [selectedItem, setSelectedItem] = useState<InventoryItem | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [showItemSelector, setShowItemSelector] = useState(false);
  const [formData, setFormData] = useState<StockAdjustmentFormData>({
    item_id: 0,
    adjustment: 0,
    adjustment_type: 'received',
    reason: '',
    project_id: undefined,
    reference: '',
  });
  const [validationError, setValidationError] = useState<string>('');
  const [showSuccess, setShowSuccess] = useState(false);

  const adjustmentTypes: AdjustmentTypeOption[] = [
    {
      value: 'received',
      label: 'Received',
      description: 'Stock received from supplier',
      icon: '📦',
      color: 'green',
      allowNegative: false,
    },
    {
      value: 'consumed',
      label: 'Consumed',
      description: 'Used in project or service',
      icon: '🔧',
      color: 'blue',
      allowNegative: false,
    },
    {
      value: 'damaged',
      label: 'Damaged',
      description: 'Damaged or defective items',
      icon: '⚠️',
      color: 'orange',
      allowNegative: false,
    },
    {
      value: 'lost',
      label: 'Lost',
      description: 'Missing or lost items',
      icon: '❌',
      color: 'red',
      allowNegative: false,
    },
    {
      value: 'returned',
      label: 'Returned',
      description: 'Items returned to inventory',
      icon: '↩️',
      color: 'purple',
      allowNegative: false,
    },
    {
      value: 'transfer',
      label: 'Transfer',
      description: 'Transfer between locations',
      icon: '🔄',
      color: 'teal',
      allowNegative: true,
    },
    {
      value: 'correction',
      label: 'Correction',
      description: 'Inventory count correction',
      icon: '✏️',
      color: 'gray',
      allowNegative: true,
    },
    {
      value: 'initial',
      label: 'Initial Stock',
      description: 'Set initial stock level',
      icon: '🎯',
      color: 'indigo',
      allowNegative: false,
    },
  ];

  // Load inventory on mount
  useEffect(() => {
    if (inventory.length === 0) {
      dispatch(fetchInventory({ per_page: 100 }) as any);
    }
  }, [dispatch, inventory.length]);

  // Filter inventory based on search query
  const filteredInventory = inventory.filter((item: InventoryItem) =>
    item.item_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    item.sku.toLowerCase().includes(searchQuery.toLowerCase())
  );

  // TODO: Use selectedAdjustmentType for UI display
  // Get selected adjustment type details
  // const selectedAdjustmentType = adjustmentTypes.find(
  //   (type) => type.value === formData.adjustment_type
  // );

  // Calculate new stock level
  const calculateNewStock = (): number => {
    if (!selectedItem) return 0;

    let adjustmentValue = formData.adjustment;

    // For types that decrease stock, make adjustment negative
    if (['consumed', 'damaged', 'lost'].includes(formData.adjustment_type)) {
      adjustmentValue = -Math.abs(adjustmentValue);
    } else if (['received', 'returned', 'initial'].includes(formData.adjustment_type)) {
      adjustmentValue = Math.abs(adjustmentValue);
    }

    return selectedItem.quantity_on_hand + adjustmentValue;
  };

  const handleItemSelect = (item: InventoryItem) => {
    setSelectedItem(item);
    setFormData({ ...formData, item_id: item.id });
    setShowItemSelector(false);
    setSearchQuery('');
  };

  const handleAdjustmentTypeChange = (type: StockAdjustmentFormData['adjustment_type']) => {
    setFormData({ ...formData, adjustment_type: type });
    setValidationError('');
  };

  const handleAdjustmentChange = (value: string) => {
    const numValue = parseFloat(value) || 0;
    setFormData({ ...formData, adjustment: numValue });
    setValidationError('');
  };

  const validateForm = (): boolean => {
    if (!selectedItem) {
      setValidationError('Please select an inventory item');
      return false;
    }

    if (formData.adjustment === 0) {
      setValidationError('Adjustment quantity must be greater than zero');
      return false;
    }

    const newStock = calculateNewStock();
    if (newStock < 0) {
      setValidationError(
        `Insufficient stock. Current: ${selectedItem.quantity_on_hand}, Adjustment: ${formData.adjustment}`
      );
      return false;
    }

    if (!formData.reason.trim()) {
      setValidationError('Please provide a reason for this adjustment');
      return false;
    }

    return true;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    try {
      let adjustmentValue = formData.adjustment;

      // Calculate correct adjustment value based on type
      if (['consumed', 'damaged', 'lost'].includes(formData.adjustment_type)) {
        adjustmentValue = -Math.abs(adjustmentValue);
      } else if (['received', 'returned', 'initial'].includes(formData.adjustment_type)) {
        adjustmentValue = Math.abs(adjustmentValue);
      }

      await dispatch(
        adjustStock({
          id: formData.item_id,
          adjustment: adjustmentValue,
          adjustment_type: formData.adjustment_type,
          reason: formData.reason,
          project_id: formData.project_id,
          reference: formData.reference,
        }) as any
      ).unwrap();

      // Refresh stock history for the item
      dispatch(fetchStockHistory({ id: formData.item_id, per_page: 10 }) as any);

      // Show success message
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 3000);

      // Reset form
      setFormData({
        item_id: 0,
        adjustment: 0,
        adjustment_type: 'received',
        reason: '',
        project_id: undefined,
        reference: '',
      });
      setSelectedItem(null);
      setValidationError('');
    } catch (err) {
      setValidationError('Failed to adjust stock. Please try again.');
    }
  };

  const newStockLevel = calculateNewStock();
  const stockChange = selectedItem ? newStockLevel - selectedItem.quantity_on_hand : 0;

  return (
    <div className="stock-adjustment">
      <div className="stock-adjustment__header">
        <h2>Adjust Stock</h2>
        <p className="subtitle">Update inventory quantities with tracking</p>
      </div>

      {showSuccess && (
        <div className="alert alert--success">
          <span className="alert__icon">✓</span>
          <span>Stock adjusted successfully!</span>
        </div>
      )}

      {error && (
        <div className="alert alert--error">
          <span className="alert__icon">❌</span>
          <span>{error}</span>
          <button onClick={() => dispatch(clearError())} className="alert__close">×</button>
        </div>
      )}

      <form onSubmit={handleSubmit} className="stock-adjustment__form">
        {/* Item Selection */}
        <div className="form-group">
          <label className="form-label">Select Item *</label>
          {selectedItem ? (
            <div className="selected-item-card">
              <div className="selected-item-card__main">
                <div className="selected-item-card__info">
                  <h4>{selectedItem.item_name}</h4>
                  <p className="sku">SKU: {selectedItem.sku}</p>
                </div>
                <div className="selected-item-card__stock">
                  <div className="stock-info">
                    <span className="label">Current Stock:</span>
                    <span className="value">{selectedItem.quantity_on_hand} {selectedItem.unit_of_measure}</span>
                  </div>
                  <div className="stock-info">
                    <span className="label">Available:</span>
                    <span className="value">{selectedItem.quantity_available} {selectedItem.unit_of_measure}</span>
                  </div>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setSelectedItem(null)}
                className="selected-item-card__remove"
              >
                ×
              </button>
            </div>
          ) : (
            <div className="item-selector">
              <input
                type="text"
                placeholder="Search by name or SKU..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                onFocus={() => setShowItemSelector(true)}
                className="form-input"
              />
              {showItemSelector && (
                <div className="item-dropdown">
                  {loading ? (
                    <div className="dropdown-loading">Loading items...</div>
                  ) : filteredInventory.length === 0 ? (
                    <div className="dropdown-empty">No items found</div>
                  ) : (
                    <ul className="item-list">
                      {filteredInventory.slice(0, 10).map((item: InventoryItem) => (
                        <li
                          key={item.id}
                          onClick={() => handleItemSelect(item)}
                          className="item-list__item"
                        >
                          <div className="item-info">
                            <span className="item-name">{item.item_name}</span>
                            <span className="item-sku">{item.sku}</span>
                          </div>
                          <div className="item-stock">
                            <span className="qty">{item.quantity_on_hand}</span>
                            <span className="unit">{item.unit_of_measure}</span>
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </div>
          )}
        </div>

        {/* Adjustment Type Selection */}
        <div className="form-group">
          <label className="form-label">Adjustment Type *</label>
          <div className="adjustment-type-grid">
            {adjustmentTypes.map((type) => (
              <button
                key={type.value}
                type="button"
                onClick={() => handleAdjustmentTypeChange(type.value)}
                className={`adjustment-type-card ${
                  formData.adjustment_type === type.value ? 'active' : ''
                } adjustment-type-card--${type.color}`}
              >
                <div className="adjustment-type-card__icon">{type.icon}</div>
                <div className="adjustment-type-card__content">
                  <h4>{type.label}</h4>
                  <p>{type.description}</p>
                </div>
              </button>
            ))}
          </div>
        </div>

        {/* Quantity Adjustment */}
        <div className="form-group">
          <label className="form-label">Quantity *</label>
          <div className="quantity-input-wrapper">
            <input
              type="number"
              min="0"
              step="0.01"
              value={formData.adjustment || ''}
              onChange={(e) => handleAdjustmentChange(e.target.value)}
              placeholder="Enter quantity"
              className="form-input form-input--large"
              required
            />
            {selectedItem && (
              <span className="quantity-unit">{selectedItem.unit_of_measure}</span>
            )}
          </div>

          {selectedItem && formData.adjustment > 0 && (
            <div className={`stock-preview ${newStockLevel < 0 ? 'stock-preview--error' : ''}`}>
              <div className="stock-preview__item">
                <span className="label">Current:</span>
                <span className="value">{selectedItem.quantity_on_hand}</span>
              </div>
              <div className="stock-preview__arrow">→</div>
              <div className="stock-preview__item">
                <span className="label">New:</span>
                <span className={`value ${stockChange > 0 ? 'positive' : 'negative'}`}>
                  {newStockLevel.toFixed(2)}
                </span>
              </div>
              <div className="stock-preview__change">
                <span className={stockChange > 0 ? 'positive' : 'negative'}>
                  ({stockChange > 0 ? '+' : ''}{stockChange.toFixed(2)})
                </span>
              </div>
            </div>
          )}
        </div>

        {/* Reason */}
        <div className="form-group">
          <label className="form-label">Reason *</label>
          <textarea
            value={formData.reason}
            onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
            placeholder="Explain the reason for this adjustment"
            rows={3}
            className="form-textarea"
            required
          />
        </div>

        {/* Reference (Optional) */}
        <div className="form-group">
          <label className="form-label">Reference (Optional)</label>
          <input
            type="text"
            value={formData.reference || ''}
            onChange={(e) => setFormData({ ...formData, reference: e.target.value })}
            placeholder="PO number, invoice, etc."
            className="form-input"
          />
        </div>

        {/* Project ID (Optional) */}
        <div className="form-group">
          <label className="form-label">Project ID (Optional)</label>
          <input
            type="number"
            value={formData.project_id || ''}
            onChange={(e) => setFormData({ ...formData, project_id: parseInt(e.target.value) || undefined })}
            placeholder="Associate with project"
            className="form-input"
          />
        </div>

        {/* Validation Error */}
        {validationError && (
          <div className="alert alert--error">
            <span className="alert__icon">⚠️</span>
            <span>{validationError}</span>
          </div>
        )}

        {/* Form Actions */}
        <div className="form-actions">
          <button
            type="button"
            onClick={() => {
              setSelectedItem(null);
              setFormData({
                item_id: 0,
                adjustment: 0,
                adjustment_type: 'received',
                reason: '',
                project_id: undefined,
                reference: '',
              });
              setValidationError('');
            }}
            className="btn btn-secondary"
          >
            Reset
          </button>
          <button
            type="submit"
            disabled={loading || !selectedItem}
            className="btn btn-primary"
          >
            {loading ? 'Processing...' : 'Adjust Stock'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default StockAdjustment;
