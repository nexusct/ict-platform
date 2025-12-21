/**
 * Barcode Scanner Component
 *
 * Camera-based barcode/QR scanning for inventory management.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { useState, useRef, useEffect, useCallback } from 'react';

interface BarcodeScannerProps {
  onScan: (result: ScanResult) => void;
  onError?: (error: string) => void;
  scanTypes?: ScanType[];
  continuous?: boolean;
  showPreview?: boolean;
}

interface ScanResult {
  value: string;
  format: string;
  timestamp: number;
}

type ScanType = 'barcode' | 'qrcode' | 'all';

const BarcodeScanner: React.FC<BarcodeScannerProps> = ({
  onScan,
  onError,
  scanTypes = ['all'],
  continuous = false,
  showPreview = true,
}) => {
  const [isActive, setIsActive] = useState(false);
  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [lastScan, setLastScan] = useState<ScanResult | null>(null);
  const [manualInput, setManualInput] = useState('');
  const [showManualInput, setShowManualInput] = useState(false);

  const videoRef = useRef<HTMLVideoElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const scanIntervalRef = useRef<number | null>(null);

  // Request camera permission
  const requestPermission = useCallback(async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
      });
      streamRef.current = stream;
      setHasPermission(true);

      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play();
      }

      return true;
    } catch (error) {
      console.error('Camera permission denied:', error);
      setHasPermission(false);
      onError?.('Camera permission denied');
      return false;
    }
  }, [onError]);

  // Start scanning
  const startScanning = useCallback(async () => {
    const permitted = await requestPermission();
    if (!permitted) return;

    setIsActive(true);

    // Check if BarcodeDetector API is available
    if ('BarcodeDetector' in window) {
      const formats = scanTypes.includes('all')
        ? ['qr_code', 'ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e']
        : scanTypes.includes('qrcode')
        ? ['qr_code']
        : ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e'];

      const barcodeDetector = new (window as any).BarcodeDetector({ formats });

      scanIntervalRef.current = setInterval(async () => {
        if (!videoRef.current || !canvasRef.current) return;

        const canvas = canvasRef.current;
        const video = videoRef.current;
        const context = canvas.getContext('2d');

        if (!context) return;

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0);

        try {
          const barcodes = await barcodeDetector.detect(canvas);
          if (barcodes.length > 0) {
            const result: ScanResult = {
              value: barcodes[0].rawValue,
              format: barcodes[0].format,
              timestamp: Date.now(),
            };

            setLastScan(result);
            onScan(result);

            if (!continuous) {
              stopScanning();
            }
          }
        } catch (error) {
          console.error('Barcode detection error:', error);
        }
      }, 100) as unknown as number;
    } else {
      // Fallback: prompt for manual input
      setShowManualInput(true);
      onError?.('Barcode scanning not supported in this browser. Please enter manually.');
    }
  }, [scanTypes, continuous, onScan, onError, requestPermission]);

  // Stop scanning
  const stopScanning = useCallback(() => {
    setIsActive(false);

    if (scanIntervalRef.current) {
      clearInterval(scanIntervalRef.current);
      scanIntervalRef.current = null;
    }

    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }
  }, []);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      stopScanning();
    };
  }, [stopScanning]);

  // Handle manual input
  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!manualInput.trim()) return;

    const result: ScanResult = {
      value: manualInput.trim(),
      format: 'manual',
      timestamp: Date.now(),
    };

    setLastScan(result);
    onScan(result);
    setManualInput('');

    if (!continuous) {
      setShowManualInput(false);
    }
  };

  return (
    <div className="ict-barcode-scanner">
      {/* Scanner viewport */}
      {showPreview && (
        <div className={`ict-scanner-viewport ${isActive ? 'active' : ''}`}>
          <video ref={videoRef} playsInline muted />
          <canvas ref={canvasRef} style={{ display: 'none' }} />

          {isActive && (
            <div className="ict-scanner-overlay">
              <div className="ict-scanner-frame">
                <div className="ict-scanner-line" />
              </div>
              <span className="ict-scanner-hint">
                Position barcode within the frame
              </span>
            </div>
          )}

          {!isActive && hasPermission === null && (
            <div className="ict-scanner-placeholder">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <path d="M7 7h0M7 12h0M7 17h0M12 7h0M12 12h0M12 17h0M17 7h0M17 12h0M17 17h0" strokeWidth="3" strokeLinecap="round" />
              </svg>
              <span>Click to start scanning</span>
            </div>
          )}

          {hasPermission === false && (
            <div className="ict-scanner-error">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                <circle cx="12" cy="12" r="10" />
                <path d="M15 9l-6 6M9 9l6 6" />
              </svg>
              <span>Camera access denied</span>
              <button onClick={requestPermission}>Try Again</button>
            </div>
          )}
        </div>
      )}

      {/* Controls */}
      <div className="ict-scanner-controls">
        {!isActive ? (
          <button className="ict-scanner-btn primary" onClick={startScanning}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="3" y="3" width="18" height="18" rx="2" />
              <path d="M7 7h0M7 12h0M7 17h0M12 7h0M12 12h0M12 17h0M17 7h0M17 12h0M17 17h0" strokeWidth="3" strokeLinecap="round" />
            </svg>
            Start Scanning
          </button>
        ) : (
          <button className="ict-scanner-btn danger" onClick={stopScanning}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <rect x="6" y="6" width="12" height="12" rx="1" />
            </svg>
            Stop Scanning
          </button>
        )}

        <button
          className="ict-scanner-btn secondary"
          onClick={() => setShowManualInput(!showManualInput)}
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
          Enter Manually
        </button>
      </div>

      {/* Manual input */}
      {showManualInput && (
        <form className="ict-scanner-manual" onSubmit={handleManualSubmit}>
          <input
            type="text"
            value={manualInput}
            onChange={(e) => setManualInput(e.target.value)}
            placeholder="Enter barcode or item number..."
            autoFocus
          />
          <button type="submit" disabled={!manualInput.trim()}>
            Submit
          </button>
        </form>
      )}

      {/* Last scan result */}
      {lastScan && (
        <div className="ict-scanner-result">
          <div className="ict-scanner-result-header">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
            </svg>
            <span>Last Scanned</span>
          </div>
          <div className="ict-scanner-result-value">{lastScan.value}</div>
          <div className="ict-scanner-result-meta">
            <span>Format: {lastScan.format}</span>
            <span>{new Date(lastScan.timestamp).toLocaleTimeString()}</span>
          </div>
        </div>
      )}

      <style>{`
        .ict-barcode-scanner {
          display: flex;
          flex-direction: column;
          gap: 16px;
        }

        .ict-scanner-viewport {
          position: relative;
          width: 100%;
          max-width: 400px;
          aspect-ratio: 4/3;
          background: #000;
          border-radius: 12px;
          overflow: hidden;
          margin: 0 auto;
        }

        .ict-scanner-viewport video {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .ict-scanner-overlay {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
        }

        .ict-scanner-frame {
          width: 70%;
          aspect-ratio: 1;
          border: 2px solid rgba(255, 255, 255, 0.8);
          border-radius: 16px;
          position: relative;
          overflow: hidden;
        }

        .ict-scanner-line {
          position: absolute;
          left: 0;
          right: 0;
          height: 2px;
          background: #ef4444;
          box-shadow: 0 0 8px #ef4444;
          animation: scan 2s ease-in-out infinite;
        }

        @keyframes scan {
          0%, 100% { top: 0; }
          50% { top: 100%; }
        }

        .ict-scanner-hint {
          margin-top: 16px;
          color: #fff;
          font-size: 13px;
          text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .ict-scanner-placeholder,
        .ict-scanner-error {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          gap: 12px;
          color: var(--ict-text-muted, #9ca3af);
          cursor: pointer;
        }

        .ict-scanner-error {
          color: #ef4444;
          cursor: default;
        }

        .ict-scanner-error button {
          margin-top: 8px;
          padding: 8px 16px;
          background: var(--ict-primary, #3b82f6);
          color: #fff;
          border: none;
          border-radius: 6px;
          cursor: pointer;
        }

        .ict-scanner-controls {
          display: flex;
          gap: 12px;
          justify-content: center;
        }

        .ict-scanner-btn {
          display: flex;
          align-items: center;
          gap: 8px;
          padding: 12px 20px;
          font-size: 14px;
          font-weight: 500;
          border: none;
          border-radius: 8px;
          cursor: pointer;
          transition: all 0.2s;
        }

        .ict-scanner-btn.primary {
          background: var(--ict-primary, #3b82f6);
          color: #fff;
        }

        .ict-scanner-btn.primary:hover {
          background: var(--ict-primary-hover, #2563eb);
        }

        .ict-scanner-btn.secondary {
          background: var(--ict-bg-secondary, #f3f4f6);
          color: var(--ict-text-color, #1f2937);
          border: 1px solid var(--ict-border-color, #e5e7eb);
        }

        .ict-scanner-btn.secondary:hover {
          background: var(--ict-bg-hover, #e5e7eb);
        }

        .ict-scanner-btn.danger {
          background: #ef4444;
          color: #fff;
        }

        .ict-scanner-btn.danger:hover {
          background: #dc2626;
        }

        .ict-scanner-manual {
          display: flex;
          gap: 8px;
          max-width: 400px;
          margin: 0 auto;
        }

        .ict-scanner-manual input {
          flex: 1;
          padding: 12px 16px;
          font-size: 14px;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 8px;
          background: var(--ict-bg-color, #fff);
          color: var(--ict-text-color, #1f2937);
        }

        .ict-scanner-manual input:focus {
          outline: none;
          border-color: var(--ict-primary, #3b82f6);
          box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .ict-scanner-manual button {
          padding: 12px 20px;
          background: var(--ict-primary, #3b82f6);
          color: #fff;
          border: none;
          border-radius: 8px;
          font-weight: 500;
          cursor: pointer;
          transition: background 0.2s;
        }

        .ict-scanner-manual button:hover:not(:disabled) {
          background: var(--ict-primary-hover, #2563eb);
        }

        .ict-scanner-manual button:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }

        .ict-scanner-result {
          max-width: 400px;
          margin: 0 auto;
          padding: 16px;
          background: #f0fdf4;
          border: 1px solid #bbf7d0;
          border-radius: 8px;
        }

        .ict-scanner-result-header {
          display: flex;
          align-items: center;
          gap: 8px;
          color: #16a34a;
          font-size: 12px;
          font-weight: 600;
          text-transform: uppercase;
          margin-bottom: 8px;
        }

        .ict-scanner-result-value {
          font-size: 18px;
          font-weight: 600;
          font-family: monospace;
          color: var(--ict-text-color, #1f2937);
          word-break: break-all;
        }

        .ict-scanner-result-meta {
          display: flex;
          justify-content: space-between;
          margin-top: 8px;
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
        }
      `}</style>
    </div>
  );
};

export default BarcodeScanner;
