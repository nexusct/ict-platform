import React, { useEffect, useState } from 'react';
import { reportsAPI } from '../../src/services/api';

type HealthData = {
  duplicates: { projects_by_name: number };
  integrity: { orphan_time_entries: number };
  anomalies: { negative_hours: number };
  inventory: { low_stock: number; threshold: number };
  sync: { errors_last_24h: number };
};

const DataHealthDashboard: React.FC = () => {
  const [data, setData] = useState<HealthData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await reportsAPI.getDataHealth();
        // @ts-ignore - APIResponse wrapper
        if (res && (res as any).data) {
          // @ts-ignore
          setData((res as any).data.data);
        } else {
          setError('Failed to load data');
        }
      } catch (e: any) {
        setError(e?.message || 'Failed to load');
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  if (loading) return <div>Loading data health…</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
  if (!data) return <div>No data</div>;

  return (
    <div className="ict-data-health">
      <h2>Data Health</h2>
      <div className="ict-cards">
        <div className="ict-card">
          <h3>Duplicates</h3>
          <p>Projects by Name: {data.duplicates.projects_by_name}</p>
        </div>
        <div className="ict-card">
          <h3>Integrity</h3>
          <p>Orphan Time Entries: {data.integrity.orphan_time_entries}</p>
        </div>
        <div className="ict-card">
          <h3>Anomalies</h3>
          <p>Negative Hours: {data.anomalies.negative_hours}</p>
        </div>
        <div className="ict-card">
          <h3>Inventory</h3>
          <p>Low Stock: {data.inventory.low_stock} (≤ {data.inventory.threshold})</p>
        </div>
        <div className="ict-card">
          <h3>Sync</h3>
          <p>Errors (24h): {data.sync.errors_last_24h}</p>
        </div>
      </div>
    </div>
  );
};

export default DataHealthDashboard;

