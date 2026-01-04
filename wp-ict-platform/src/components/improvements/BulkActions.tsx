/**
 * Bulk Actions Component
 *
 * Handles bulk operations for lists (edit, delete, approve, sync).
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import { useState, useCallback } from 'react';
import type { BulkOperationResult } from '../../types';

interface BulkActionsProps {
  selectedIds: number[];
  entityType: string;
  onClearSelection: () => void;
  onOperationComplete?: (result: BulkOperationResult) => void;
  availableActions?: BulkActionConfig[];
  apiEndpoint?: string;
}

interface BulkActionConfig {
  id: string;
  label: string;
  icon?: string;
  variant?: 'default' | 'danger' | 'success';
  confirmMessage?: string;
  requiresInput?: boolean;
  inputLabel?: string;
}

const defaultActions: BulkActionConfig[] = [
  { id: 'delete', label: 'Delete', icon: '🗑️', variant: 'danger', confirmMessage: 'Are you sure you want to delete these items?' },
  { id: 'approve', label: 'Approve', icon: '✓', variant: 'success' },
  { id: 'sync', label: 'Sync to Zoho', icon: '🔄' },
];

function BulkActions({
  selectedIds,
  entityType,
  onClearSelection,
  onOperationComplete,
  availableActions = defaultActions,
  apiEndpoint = '/wp-json/ict/v1',
}: BulkActionsProps) {
  const [isProcessing, setIsProcessing] = useState(false);
  const [progress, setProgress] = useState({ current: 0, total: 0 });
  const [showConfirm, setShowConfirm] = useState<BulkActionConfig | null>(null);
  const [inputValue, setInputValue] = useState('');

  const executeBulkAction = useCallback(
    async (action: BulkActionConfig) => {
      if (selectedIds.length === 0) return;

      setIsProcessing(true);
      setProgress({ current: 0, total: selectedIds.length });

      const results: BulkOperationResult = {
        success_count: 0,
        error_count: 0,
        errors: [],
      };

      try {
        // Process in batches of 10
        const batchSize = 10;
        for (let i = 0; i < selectedIds.length; i += batchSize) {
          const batch = selectedIds.slice(i, i + batchSize);

          const response = await fetch(`${apiEndpoint}/${entityType}/bulk`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': (window as any).ictSettings?.nonce || '',
            },
            body: JSON.stringify({
              action: action.id,
              ids: batch,
              data: action.requiresInput ? { value: inputValue } : undefined,
            }),
          });

          const data = await response.json();

          if (data.success) {
            results.success_count += batch.length;
          } else {
            results.error_count += batch.length;
            batch.forEach((id) => {
              results.errors.push({ id, error: data.message || 'Unknown error' });
            });
          }

          setProgress({ current: Math.min(i + batchSize, selectedIds.length), total: selectedIds.length });

          // Small delay between batches
          if (i + batchSize < selectedIds.length) {
            await new Promise((resolve) => setTimeout(resolve, 100));
          }
        }

        onOperationComplete?.(results);
        onClearSelection();
      } catch (error) {
        console.error('Bulk action error:', error);
        results.error_count = selectedIds.length;
      } finally {
        setIsProcessing(false);
        setProgress({ current: 0, total: 0 });
        setShowConfirm(null);
        setInputValue('');
      }
    },
    [selectedIds, entityType, apiEndpoint, inputValue, onOperationComplete, onClearSelection]
  );

  const handleActionClick = (action: BulkActionConfig) => {
    if (action.confirmMessage || action.requiresInput) {
      setShowConfirm(action);
    } else {
      executeBulkAction(action);
    }
  };

  if (selectedIds.length === 0) {
    return null;
  }

  return (
    <>
      <div className="ict-bulk-actions">
        <div className="ict-bulk-actions-info">
          <span className="ict-bulk-count">{selectedIds.length}</span>
          <span>items selected</span>
          <button className="ict-bulk-clear" onClick={onClearSelection}>
            Clear
          </button>
        </div>

        <div className="ict-bulk-actions-buttons">
          {availableActions.map((action) => (
            <button
              key={action.id}
              className={`ict-bulk-action-btn ${action.variant || 'default'}`}
              onClick={() => handleActionClick(action)}
              disabled={isProcessing}
            >
              {action.icon && <span className="ict-bulk-action-icon">{action.icon}</span>}
              {action.label}
            </button>
          ))}
        </div>

        {isProcessing && (
          <div className="ict-bulk-progress">
            <div className="ict-bulk-progress-bar">
              <div
                className="ict-bulk-progress-fill"
                style={{ width: `${(progress.current / progress.total) * 100}%` }}
              />
            </div>
            <span className="ict-bulk-progress-text">
              {progress.current} / {progress.total}
            </span>
          </div>
        )}
      </div>

      {showConfirm && (
        <div className="ict-bulk-confirm-overlay">
          <div className="ict-bulk-confirm-dialog">
            <h3 className="ict-bulk-confirm-title">
              {showConfirm.label} {selectedIds.length} items?
            </h3>

            {showConfirm.confirmMessage && (
              <p className="ict-bulk-confirm-message">{showConfirm.confirmMessage}</p>
            )}

            {showConfirm.requiresInput && (
              <div className="ict-bulk-confirm-input">
                <label>{showConfirm.inputLabel || 'Value'}</label>
                <input
                  type="text"
                  value={inputValue}
                  onChange={(e) => setInputValue(e.target.value)}
                  placeholder="Enter value..."
                />
              </div>
            )}

            <div className="ict-bulk-confirm-actions">
              <button
                className="ict-bulk-confirm-cancel"
                onClick={() => {
                  setShowConfirm(null);
                  setInputValue('');
                }}
              >
                Cancel
              </button>
              <button
                className={`ict-bulk-confirm-proceed ${showConfirm.variant || 'default'}`}
                onClick={() => executeBulkAction(showConfirm)}
                disabled={showConfirm.requiresInput && !inputValue}
              >
                {showConfirm.label}
              </button>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .ict-bulk-actions {
          position: sticky;
          bottom: 0;
          left: 0;
          right: 0;
          background: var(--ict-bg-color, #fff);
          border-top: 1px solid var(--ict-border-color, #e5e7eb);
          padding: 12px 16px;
          display: flex;
          align-items: center;
          gap: 16px;
          box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
          z-index: var(--ict-zindex-sticky, 89100);
        }

        .ict-bulk-actions-info {
          display: flex;
          align-items: center;
          gap: 8px;
          font-size: 14px;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-bulk-count {
          font-weight: 600;
          background: var(--ict-primary, #3b82f6);
          color: #fff;
          padding: 2px 8px;
          border-radius: 12px;
          font-size: 12px;
        }

        .ict-bulk-clear {
          background: none;
          border: none;
          color: var(--ict-primary, #3b82f6);
          cursor: pointer;
          font-size: 13px;
          padding: 4px 8px;
        }

        .ict-bulk-clear:hover {
          text-decoration: underline;
        }

        .ict-bulk-actions-buttons {
          display: flex;
          gap: 8px;
          flex: 1;
        }

        .ict-bulk-action-btn {
          display: flex;
          align-items: center;
          gap: 6px;
          padding: 8px 16px;
          font-size: 13px;
          font-weight: 500;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 6px;
          background: var(--ict-bg-color, #fff);
          color: var(--ict-text-color, #1f2937);
          cursor: pointer;
          transition: all 0.15s;
        }

        .ict-bulk-action-btn:hover {
          background: var(--ict-bg-hover, #f3f4f6);
        }

        .ict-bulk-action-btn:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }

        .ict-bulk-action-btn.danger {
          color: #dc2626;
          border-color: #fecaca;
        }

        .ict-bulk-action-btn.danger:hover {
          background: #fef2f2;
        }

        .ict-bulk-action-btn.success {
          color: #16a34a;
          border-color: #bbf7d0;
        }

        .ict-bulk-action-btn.success:hover {
          background: #f0fdf4;
        }

        .ict-bulk-action-icon {
          font-size: 14px;
        }

        .ict-bulk-progress {
          display: flex;
          align-items: center;
          gap: 12px;
          min-width: 150px;
        }

        .ict-bulk-progress-bar {
          flex: 1;
          height: 6px;
          background: var(--ict-bg-secondary, #e5e7eb);
          border-radius: 3px;
          overflow: hidden;
        }

        .ict-bulk-progress-fill {
          height: 100%;
          background: var(--ict-primary, #3b82f6);
          transition: width 0.3s ease;
        }

        .ict-bulk-progress-text {
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
          white-space: nowrap;
        }

        .ict-bulk-confirm-overlay {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: var(--ict-zindex-modal, 89600);
        }

        .ict-bulk-confirm-dialog {
          background: var(--ict-bg-color, #fff);
          border-radius: 12px;
          padding: 24px;
          max-width: 400px;
          width: 90%;
          box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .ict-bulk-confirm-title {
          font-size: 18px;
          font-weight: 600;
          margin: 0 0 8px;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-bulk-confirm-message {
          font-size: 14px;
          color: var(--ict-text-muted, #6b7280);
          margin: 0 0 16px;
        }

        .ict-bulk-confirm-input {
          margin-bottom: 16px;
        }

        .ict-bulk-confirm-input label {
          display: block;
          font-size: 13px;
          font-weight: 500;
          margin-bottom: 6px;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-bulk-confirm-input input {
          width: 100%;
          padding: 10px 12px;
          font-size: 14px;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 6px;
        }

        .ict-bulk-confirm-actions {
          display: flex;
          gap: 12px;
          justify-content: flex-end;
        }

        .ict-bulk-confirm-cancel,
        .ict-bulk-confirm-proceed {
          padding: 10px 20px;
          font-size: 14px;
          font-weight: 500;
          border-radius: 6px;
          cursor: pointer;
          transition: all 0.15s;
        }

        .ict-bulk-confirm-cancel {
          background: none;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          color: var(--ict-text-color, #1f2937);
        }

        .ict-bulk-confirm-cancel:hover {
          background: var(--ict-bg-hover, #f3f4f6);
        }

        .ict-bulk-confirm-proceed {
          background: var(--ict-primary, #3b82f6);
          border: none;
          color: #fff;
        }

        .ict-bulk-confirm-proceed:hover {
          background: var(--ict-primary-hover, #2563eb);
        }

        .ict-bulk-confirm-proceed.danger {
          background: #dc2626;
        }

        .ict-bulk-confirm-proceed.danger:hover {
          background: #b91c1c;
        }

        .ict-bulk-confirm-proceed:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }
      `}</style>
    </>
  );
}

export default BulkActions;
