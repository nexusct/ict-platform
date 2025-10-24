/**
 * SkillMatrix Component
 *
 * Visual matrix for managing technician skills and proficiency levels
 * Features:
 * - Grid view of technicians and their skills
 * - 5-star proficiency rating system
 * - Add/edit/delete skills
 * - Filter and search technicians
 * - Certification tracking
 * - Years of experience
 * - Skill recommendations
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../../store/hooks';
import {
  fetchTechnicianSkills,
  updateTechnicianSkills,
  selectTechnicianSkills,
  // selectTechnicianSkillsById, // TODO: Use for individual technician view
  selectResourcesLoading,
  selectResourcesError,
} from '../../store/slices/resourcesSlice';
import { TechnicianSkill } from '../../types';

interface SkillMatrixProps {
  technicianIds?: number[];
  editable?: boolean;
  onSkillClick?: (technicianId: number, skillId: number) => void;
}

const SkillMatrix: React.FC<SkillMatrixProps> = ({
  technicianIds = [],
  editable = false,
  onSkillClick,
}) => {
  const dispatch = useAppDispatch();

  const allSkills = useAppSelector(selectTechnicianSkills);
  const loading = useAppSelector(selectResourcesLoading);
  const error = useAppSelector(selectResourcesError);

  const [selectedTechnicianId, setSelectedTechnicianId] = useState<number | null>(null);
  const [editingSkill, setEditingSkill] = useState<Partial<TechnicianSkill> | null>(null);
  const [showAddModal, setShowAddModal] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  // Common skill categories
  const commonSkills = [
    'Electrical Installation',
    'Panel Wiring',
    'Troubleshooting',
    'PLC Programming',
    'HMI Configuration',
    'Motor Control',
    'VFD Setup',
    'Network Cabling',
    'Fiber Optics',
    'AutoCAD',
    'Safety Compliance',
    'Project Management',
  ];

  // Fetch skills for all technicians on mount
  useEffect(() => {
    technicianIds.forEach((id) => {
      dispatch(fetchTechnicianSkills(id));
    });
  }, [dispatch, technicianIds]);

  // Get all unique skill names
  const getAllSkillNames = (): string[] => {
    const skillNames = new Set<string>();

    Object.values(allSkills).forEach((techSkills: unknown) => {
      if (Array.isArray(techSkills)) {
        techSkills.forEach((skill: any) => {
          skillNames.add(skill.skill_name);
        });
      }
    });

    return Array.from(skillNames).sort();
  };

  // Get skill for specific technician
  const getTechnicianSkill = (technicianId: number, skillName: string): TechnicianSkill | undefined => {
    const techSkills = allSkills[technicianId] || [];
    return techSkills.find((s: any) => s.skill_name === skillName);
  };

  // Render proficiency stars
  const renderProficiencyStars = (level: number, editable: boolean = false, onChange?: (level: number) => void) => {
    return (
      <div className="ict-skill-matrix__stars">
        {[1, 2, 3, 4, 5].map((star) => (
          <span
            key={star}
            className={`dashicons ${star <= level ? 'dashicons-star-filled' : 'dashicons-star-empty'} ${editable ? 'ict-skill-matrix__star--editable' : ''}`}
            onClick={() => editable && onChange && onChange(star)}
            style={{ cursor: editable ? 'pointer' : 'default' }}
          />
        ))}
      </div>
    );
  };

  // Handle skill update
  const handleUpdateSkill = async (technicianId: number, skill: Partial<TechnicianSkill>) => {
    const currentSkills = allSkills[technicianId] || [];
    const existingSkillIndex = currentSkills.findIndex((s: any) => s.skill_name === skill.skill_name);

    let updatedSkills: Partial<TechnicianSkill>[];

    if (existingSkillIndex >= 0) {
      // Update existing skill
      updatedSkills = currentSkills.map((s: Partial<TechnicianSkill>, index: number) =>
        index === existingSkillIndex ? { ...s, ...skill } : s
      );
    } else {
      // Add new skill
      updatedSkills = [...currentSkills, skill];
    }

    try {
      await dispatch(
        updateTechnicianSkills({
          technicianId,
          skills: updatedSkills,
        })
      ).unwrap();
    } catch (err) {
      console.error('Failed to update skill:', err);
    }
  };

  // TODO: Implement delete skill functionality in UI
  // Handle delete skill
  // const handleDeleteSkill = async (technicianId: number, skillName: string) => {
  //   if (!confirm(`Are you sure you want to remove the skill "${skillName}"?`)) {
  //     return;
  //   }

  //   const currentSkills = allSkills[technicianId] || [];
  //   const updatedSkills = currentSkills.filter((s: Partial<TechnicianSkill>) => s.skill_name !== skillName);

  //   try {
  //     await dispatch(
  //       updateTechnicianSkills({
  //         technicianId,
  //         skills: updatedSkills,
  //       })
  //     ).unwrap();
  //   } catch (err) {
  //     console.error('Failed to delete skill:', err);
  //   }
  // };

  // Handle add skill modal
  const handleAddSkill = async (technicianId: number) => {
    setSelectedTechnicianId(technicianId);
    setEditingSkill({
      technician_id: technicianId,
      skill_name: '',
      proficiency_level: 3,
      years_experience: 0,
      certifications: '',
      last_updated: new Date().toISOString(),
    });
    setShowAddModal(true);
  };

  // Submit new skill
  const handleSubmitNewSkill = async () => {
    if (!editingSkill || !editingSkill.skill_name || !selectedTechnicianId) {
      return;
    }

    await handleUpdateSkill(selectedTechnicianId, editingSkill);
    setShowAddModal(false);
    setEditingSkill(null);
    setSelectedTechnicianId(null);
  };

  // Handle proficiency change in matrix
  const handleProficiencyChange = async (technicianId: number, skillName: string, newLevel: number) => {
    const existingSkill = getTechnicianSkill(technicianId, skillName);

    await handleUpdateSkill(technicianId, {
      ...existingSkill,
      skill_name: skillName,
      proficiency_level: newLevel,
      last_updated: new Date().toISOString(),
    });
  };

  const skillNames = getAllSkillNames();
  const filteredTechnicianIds = technicianIds.filter((_id) => {
    if (!searchTerm) return true;
    // In real implementation, would filter by technician name
    return true;
  });

  return (
    <div className="ict-skill-matrix">
      {/* Header */}
      <div className="ict-skill-matrix__header">
        <h2 className="ict-skill-matrix__title">Technician Skill Matrix</h2>

        <div className="ict-skill-matrix__controls">
          <input
            type="text"
            placeholder="Search technicians..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="ict-input ict-skill-matrix__search"
          />
        </div>
      </div>

      {/* Error message */}
      {error && (
        <div className="ict-notice ict-notice--error">
          <p>Error loading skills: {error}</p>
        </div>
      )}

      {/* Loading indicator */}
      {loading && (
        <div className="ict-skill-matrix__loading">
          <div className="ict-spinner"></div>
          <p>Loading skill data...</p>
        </div>
      )}

      {/* Skill matrix table */}
      {!loading && (
        <div className="ict-skill-matrix__table-wrapper">
          <table className="ict-skill-matrix__table">
            <thead>
              <tr>
                <th className="ict-skill-matrix__header-cell ict-skill-matrix__header-cell--technician">
                  Technician
                </th>
                {skillNames.map((skillName) => (
                  <th
                    key={skillName}
                    className="ict-skill-matrix__header-cell ict-skill-matrix__header-cell--skill"
                  >
                    <div className="ict-skill-matrix__skill-header">
                      <span className="ict-skill-matrix__skill-name">{skillName}</span>
                    </div>
                  </th>
                ))}
                {editable && (
                  <th className="ict-skill-matrix__header-cell ict-skill-matrix__header-cell--actions">
                    Actions
                  </th>
                )}
              </tr>
            </thead>
            <tbody>
              {filteredTechnicianIds.length === 0 ? (
                <tr>
                  <td colSpan={skillNames.length + (editable ? 2 : 1)} className="ict-skill-matrix__no-data">
                    No technicians found
                  </td>
                </tr>
              ) : (
                filteredTechnicianIds.map((technicianId) => {
                  const techSkills = allSkills[technicianId] || [];

                  return (
                    <tr key={technicianId} className="ict-skill-matrix__row">
                      <td className="ict-skill-matrix__cell ict-skill-matrix__cell--technician">
                        <div className="ict-skill-matrix__technician-info">
                          <span className="ict-skill-matrix__technician-name">
                            Technician #{technicianId}
                          </span>
                          <span className="ict-skill-matrix__skill-count">
                            {techSkills.length} skills
                          </span>
                        </div>
                      </td>
                      {skillNames.map((skillName) => {
                        const skill = getTechnicianSkill(technicianId, skillName);

                        return (
                          <td
                            key={skillName}
                            className={`ict-skill-matrix__cell ${skill ? 'ict-skill-matrix__cell--has-skill' : 'ict-skill-matrix__cell--no-skill'}`}
                            onClick={() => onSkillClick && skill && onSkillClick(technicianId, skill.id)}
                            title={
                              skill
                                ? `Proficiency: ${skill.proficiency_level}/5\nYears: ${skill.years_experience || 'N/A'}\nCertifications: ${skill.certifications || 'None'}`
                                : 'No skill data'
                            }
                          >
                            {skill ? (
                              <div className="ict-skill-matrix__skill-cell">
                                {renderProficiencyStars(
                                  skill.proficiency_level,
                                  editable,
                                  (newLevel) => handleProficiencyChange(technicianId, skillName, newLevel)
                                )}
                                {skill.certifications && (
                                  <span className="ict-skill-matrix__certified">
                                    <span className="dashicons dashicons-yes-alt"></span>
                                  </span>
                                )}
                              </div>
                            ) : (
                              <span className="ict-skill-matrix__no-skill-indicator">-</span>
                            )}
                          </td>
                        );
                      })}
                      {editable && (
                        <td className="ict-skill-matrix__cell ict-skill-matrix__cell--actions">
                          <button
                            className="ict-button ict-button--small ict-button--secondary"
                            onClick={() => handleAddSkill(technicianId)}
                          >
                            <span className="dashicons dashicons-plus-alt"></span>
                          </button>
                        </td>
                      )}
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Add/Edit Skill Modal */}
      {showAddModal && editingSkill && (
        <div className="ict-modal-overlay" onClick={() => setShowAddModal(false)}>
          <div className="ict-modal" onClick={(e) => e.stopPropagation()}>
            <div className="ict-modal__header">
              <h3>Add Skill</h3>
              <button className="ict-modal__close" onClick={() => setShowAddModal(false)}>
                <span className="dashicons dashicons-no-alt"></span>
              </button>
            </div>

            <div className="ict-modal__body">
              <div className="ict-form-group">
                <label>Skill Name</label>
                <select
                  value={editingSkill.skill_name}
                  onChange={(e) =>
                    setEditingSkill({ ...editingSkill, skill_name: e.target.value })
                  }
                  className="ict-select"
                >
                  <option value="">Select a skill</option>
                  {commonSkills.map((skill) => (
                    <option key={skill} value={skill}>
                      {skill}
                    </option>
                  ))}
                </select>
              </div>

              <div className="ict-form-group">
                <label>Proficiency Level</label>
                {renderProficiencyStars(editingSkill.proficiency_level || 3, true, (level) =>
                  setEditingSkill({ ...editingSkill, proficiency_level: level })
                )}
              </div>

              <div className="ict-form-group">
                <label>Years of Experience</label>
                <input
                  type="number"
                  value={editingSkill.years_experience || 0}
                  onChange={(e) =>
                    setEditingSkill({ ...editingSkill, years_experience: Number(e.target.value) })
                  }
                  min="0"
                  step="0.5"
                  className="ict-input"
                />
              </div>

              <div className="ict-form-group">
                <label>Certifications</label>
                <textarea
                  value={editingSkill.certifications || ''}
                  onChange={(e) =>
                    setEditingSkill({ ...editingSkill, certifications: e.target.value })
                  }
                  rows={3}
                  className="ict-textarea"
                  placeholder="List relevant certifications..."
                />
              </div>
            </div>

            <div className="ict-modal__footer">
              <button
                className="ict-button ict-button--primary"
                onClick={handleSubmitNewSkill}
                disabled={!editingSkill.skill_name}
              >
                Add Skill
              </button>
              <button
                className="ict-button ict-button--secondary"
                onClick={() => setShowAddModal(false)}
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Legend */}
      <div className="ict-skill-matrix__legend">
        <h4>Proficiency Levels</h4>
        <div className="ict-skill-matrix__legend-items">
          <div className="ict-skill-matrix__legend-item">
            {renderProficiencyStars(1)} <span>Beginner</span>
          </div>
          <div className="ict-skill-matrix__legend-item">
            {renderProficiencyStars(2)} <span>Basic</span>
          </div>
          <div className="ict-skill-matrix__legend-item">
            {renderProficiencyStars(3)} <span>Intermediate</span>
          </div>
          <div className="ict-skill-matrix__legend-item">
            {renderProficiencyStars(4)} <span>Advanced</span>
          </div>
          <div className="ict-skill-matrix__legend-item">
            {renderProficiencyStars(5)} <span>Expert</span>
          </div>
        </div>
      </div>

      {/* Summary */}
      {!loading && filteredTechnicianIds.length > 0 && (
        <div className="ict-skill-matrix__summary">
          <p>
            Showing <strong>{skillNames.length}</strong> skills across{' '}
            <strong>{filteredTechnicianIds.length}</strong> technician
            {filteredTechnicianIds.length !== 1 ? 's' : ''}
          </p>
        </div>
      )}
    </div>
  );
};

export default SkillMatrix;
