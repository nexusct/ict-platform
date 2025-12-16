/**
 * Time Tracking Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  Alert,
  TextInput,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useDispatch, useSelector } from 'react-redux';
import * as Location from 'expo-location';
import { useTheme } from '../context/ThemeContext';
import type { RootState, AppDispatch } from '../store';
import {
  startTimeEntry,
  stopTimeEntry,
  startBreak,
  endBreak,
  loadActiveEntry,
} from '../store/slices/timeEntriesSlice';
import { fetchProjects } from '../store/slices/projectsSlice';

export const TimeTrackingScreen: React.FC = () => {
  const { theme } = useTheme();
  const dispatch = useDispatch<AppDispatch>();

  const { activeEntry, weeklyHours, dailyHours } = useSelector(
    (state: RootState) => state.timeEntries
  );
  const { items: projects } = useSelector((state: RootState) => state.projects);

  const [elapsedTime, setElapsedTime] = useState(0);
  const [selectedProject, setSelectedProject] = useState<number | null>(null);
  const [notes, setNotes] = useState('');
  const [showProjectPicker, setShowProjectPicker] = useState(false);
  const [hourlyRate] = useState(45); // Default rate

  // Load data on mount
  useEffect(() => {
    dispatch(loadActiveEntry());
    dispatch(fetchProjects({ status: 'active' }));
  }, [dispatch]);

  // Timer effect
  useEffect(() => {
    if (!activeEntry || activeEntry.status !== 'active') return;

    const calculateElapsed = () => {
      const start = new Date(activeEntry.start_time).getTime();
      const now = Date.now();

      // Calculate break time
      const breakTime = activeEntry.breaks.reduce((total, brk) => {
        const brkStart = new Date(brk.start_time).getTime();
        const brkEnd = brk.end_time ? new Date(brk.end_time).getTime() : now;
        return total + (brkEnd - brkStart);
      }, 0);

      return Math.floor((now - start - breakTime) / 1000);
    };

    setElapsedTime(calculateElapsed());

    const intervalId = setInterval(() => {
      setElapsedTime(calculateElapsed());
    }, 1000);

    return () => clearInterval(intervalId);
  }, [activeEntry]);

  const formatTime = (seconds: number): string => {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  const formatCurrency = (amount: number): string => {
    return `$${amount.toFixed(2)}`;
  };

  const handleStart = async () => {
    if (!selectedProject) {
      Alert.alert('Select Project', 'Please select a project to track time against');
      return;
    }

    // Get location
    let location: Location.LocationObject | null = null;
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status === 'granted') {
        location = await Location.getCurrentPositionAsync({});
      }
    } catch (error) {
      console.log('Location error:', error);
    }

    dispatch(
      startTimeEntry({
        project_id: selectedProject,
        hourly_rate: hourlyRate,
        latitude: location?.coords.latitude,
        longitude: location?.coords.longitude,
      })
    );
  };

  const handleStop = () => {
    Alert.alert('Stop Timer', 'Are you sure you want to stop tracking?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Stop & Save',
        onPress: () => {
          dispatch(stopTimeEntry(notes));
          setNotes('');
          setSelectedProject(null);
        },
      },
    ]);
  };

  const handleBreak = (type: 'short' | 'lunch') => {
    if (activeEntry?.status === 'active') {
      dispatch(startBreak(type));
    } else if (activeEntry?.status === 'paused') {
      dispatch(endBreak());
    }
  };

  const currentEarnings = (elapsedTime / 3600) * hourlyRate;
  const isOvertime = dailyHours + elapsedTime / 3600 > 8;

  const styles = createStyles(theme);

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ScrollView style={styles.scrollView}>
        {/* Timer Display */}
        <View
          style={[
            styles.timerContainer,
            activeEntry?.status === 'active' && styles.timerActive,
            activeEntry?.status === 'paused' && styles.timerPaused,
          ]}
        >
          <Text style={styles.timerTime}>{formatTime(elapsedTime)}</Text>
          <Text style={styles.timerStatus}>
            {!activeEntry && 'Ready to track'}
            {activeEntry?.status === 'active' && 'Tracking...'}
            {activeEntry?.status === 'paused' && 'On Break'}
          </Text>
          {isOvertime && activeEntry && (
            <View style={styles.overtimeBadge}>
              <Text style={styles.overtimeBadgeText}>OVERTIME</Text>
            </View>
          )}
        </View>

        {/* Earnings */}
        {activeEntry && (
          <View style={styles.earningsCard}>
            <View style={styles.earningsRow}>
              <Text style={styles.earningsLabel}>Current Earnings</Text>
              <Text style={styles.earningsValue}>{formatCurrency(currentEarnings)}</Text>
            </View>
            <View style={styles.earningsRow}>
              <Text style={styles.earningsLabel}>Rate</Text>
              <Text style={styles.earningsRate}>{formatCurrency(hourlyRate)}/hr</Text>
            </View>
          </View>
        )}

        {/* Project Selector */}
        {!activeEntry && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Select Project</Text>
            <TouchableOpacity
              style={styles.projectSelector}
              onPress={() => setShowProjectPicker(!showProjectPicker)}
            >
              <Text
                style={[
                  styles.projectSelectorText,
                  !selectedProject && styles.projectSelectorPlaceholder,
                ]}
              >
                {selectedProject
                  ? projects.find((p) => p.id === selectedProject)?.name
                  : 'Tap to select project'}
              </Text>
              <Text style={styles.projectSelectorArrow}>▼</Text>
            </TouchableOpacity>

            {showProjectPicker && (
              <View style={styles.projectList}>
                {projects.map((project) => (
                  <TouchableOpacity
                    key={project.id}
                    style={[
                      styles.projectOption,
                      selectedProject === project.id && styles.projectOptionSelected,
                    ]}
                    onPress={() => {
                      setSelectedProject(project.id);
                      setShowProjectPicker(false);
                    }}
                  >
                    <Text style={styles.projectOptionNumber}>{project.project_number}</Text>
                    <Text style={styles.projectOptionName}>{project.name}</Text>
                  </TouchableOpacity>
                ))}
              </View>
            )}
          </View>
        )}

        {/* Breaks */}
        {activeEntry && activeEntry.breaks.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Breaks ({activeEntry.breaks.length})</Text>
            {activeEntry.breaks.map((brk) => (
              <View key={brk.id} style={styles.breakItem}>
                <Text style={styles.breakType}>{brk.type}</Text>
                <Text style={styles.breakTime}>
                  {new Date(brk.start_time).toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                  {brk.end_time &&
                    ` - ${new Date(brk.end_time).toLocaleTimeString([], {
                      hour: '2-digit',
                      minute: '2-digit',
                    })}`}
                </Text>
              </View>
            ))}
          </View>
        )}

        {/* Notes */}
        {activeEntry && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Notes</Text>
            <TextInput
              style={styles.notesInput}
              value={notes}
              onChangeText={setNotes}
              placeholder="Add notes about this time entry..."
              placeholderTextColor={theme.colors.textMuted}
              multiline
              numberOfLines={3}
            />
          </View>
        )}

        {/* Controls */}
        <View style={styles.controls}>
          {!activeEntry ? (
            <TouchableOpacity
              style={[styles.startButton, !selectedProject && styles.buttonDisabled]}
              onPress={handleStart}
              disabled={!selectedProject}
            >
              <Text style={styles.startButtonText}>▶ Start Tracking</Text>
            </TouchableOpacity>
          ) : activeEntry.status === 'active' ? (
            <>
              <View style={styles.breakButtons}>
                <TouchableOpacity
                  style={styles.breakButton}
                  onPress={() => handleBreak('short')}
                >
                  <Text style={styles.breakButtonText}>⏸ Short Break</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.breakButton, styles.lunchButton]}
                  onPress={() => handleBreak('lunch')}
                >
                  <Text style={styles.breakButtonText}>☕ Lunch</Text>
                </TouchableOpacity>
              </View>
              <TouchableOpacity style={styles.stopButton} onPress={handleStop}>
                <Text style={styles.stopButtonText}>⏹ Stop & Save</Text>
              </TouchableOpacity>
            </>
          ) : (
            <>
              <TouchableOpacity
                style={styles.resumeButton}
                onPress={() => handleBreak('short')}
              >
                <Text style={styles.resumeButtonText}>▶ Resume</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.stopButton} onPress={handleStop}>
                <Text style={styles.stopButtonText}>⏹ Stop & Save</Text>
              </TouchableOpacity>
            </>
          )}
        </View>

        {/* Weekly Summary */}
        <View style={styles.weeklySummary}>
          <View style={styles.weeklyBar}>
            <View
              style={[
                styles.weeklyFill,
                { width: `${Math.min(100, (weeklyHours / 40) * 100)}%` },
                weeklyHours >= 40 && styles.weeklyFillOvertime,
              ]}
            />
          </View>
          <Text style={styles.weeklyText}>
            {weeklyHours.toFixed(1)} / 40 hrs this week
          </Text>
        </View>

        <View style={{ height: 100 }} />
      </ScrollView>
    </SafeAreaView>
  );
};

const createStyles = (theme: any) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.colors.background,
    },
    scrollView: {
      flex: 1,
    },
    timerContainer: {
      alignItems: 'center',
      paddingVertical: 48,
      marginHorizontal: 20,
      marginTop: 20,
      borderRadius: 20,
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    timerActive: {
      backgroundColor: theme.colors.success,
    },
    timerPaused: {
      backgroundColor: theme.colors.warning,
    },
    timerTime: {
      fontSize: 56,
      fontWeight: '700',
      fontFamily: 'monospace',
      color: theme.colors.text,
      letterSpacing: 2,
    },
    timerStatus: {
      fontSize: 14,
      color: theme.colors.textSecondary,
      marginTop: 8,
    },
    overtimeBadge: {
      position: 'absolute',
      top: 16,
      right: 16,
      backgroundColor: theme.colors.danger,
      paddingHorizontal: 10,
      paddingVertical: 4,
      borderRadius: 8,
    },
    overtimeBadgeText: {
      color: '#fff',
      fontSize: 10,
      fontWeight: '700',
    },
    earningsCard: {
      marginHorizontal: 20,
      marginTop: 16,
      padding: 16,
      backgroundColor: theme.colors.card,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    earningsRow: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      paddingVertical: 8,
    },
    earningsLabel: {
      fontSize: 14,
      color: theme.colors.textSecondary,
    },
    earningsValue: {
      fontSize: 20,
      fontWeight: '700',
      color: theme.colors.success,
    },
    earningsRate: {
      fontSize: 14,
      color: theme.colors.text,
    },
    section: {
      marginHorizontal: 20,
      marginTop: 24,
    },
    sectionTitle: {
      fontSize: 16,
      fontWeight: '600',
      color: theme.colors.text,
      marginBottom: 12,
    },
    projectSelector: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      padding: 16,
      backgroundColor: theme.colors.card,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    projectSelectorText: {
      fontSize: 16,
      color: theme.colors.text,
    },
    projectSelectorPlaceholder: {
      color: theme.colors.textMuted,
    },
    projectSelectorArrow: {
      color: theme.colors.textMuted,
    },
    projectList: {
      marginTop: 8,
      backgroundColor: theme.colors.card,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
      overflow: 'hidden',
    },
    projectOption: {
      padding: 16,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.borderLight,
    },
    projectOptionSelected: {
      backgroundColor: theme.colors.primaryLight,
    },
    projectOptionNumber: {
      fontSize: 12,
      color: theme.colors.textMuted,
    },
    projectOptionName: {
      fontSize: 15,
      color: theme.colors.text,
      marginTop: 2,
    },
    breakItem: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      padding: 12,
      backgroundColor: theme.colors.card,
      borderRadius: 8,
      marginBottom: 8,
    },
    breakType: {
      fontSize: 14,
      fontWeight: '500',
      color: theme.colors.text,
      textTransform: 'capitalize',
    },
    breakTime: {
      fontSize: 14,
      color: theme.colors.textMuted,
    },
    notesInput: {
      padding: 16,
      backgroundColor: theme.colors.card,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
      fontSize: 14,
      color: theme.colors.text,
      minHeight: 80,
      textAlignVertical: 'top',
    },
    controls: {
      marginHorizontal: 20,
      marginTop: 32,
    },
    startButton: {
      backgroundColor: theme.colors.success,
      paddingVertical: 18,
      borderRadius: 12,
      alignItems: 'center',
    },
    startButtonText: {
      color: '#fff',
      fontSize: 18,
      fontWeight: '600',
    },
    buttonDisabled: {
      opacity: 0.5,
    },
    breakButtons: {
      flexDirection: 'row',
      gap: 12,
      marginBottom: 12,
    },
    breakButton: {
      flex: 1,
      backgroundColor: theme.colors.card,
      paddingVertical: 14,
      borderRadius: 12,
      alignItems: 'center',
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    lunchButton: {
      backgroundColor: 'rgba(245, 158, 11, 0.1)',
      borderColor: theme.colors.warning,
    },
    breakButtonText: {
      fontSize: 14,
      fontWeight: '600',
      color: theme.colors.text,
    },
    stopButton: {
      backgroundColor: theme.colors.danger,
      paddingVertical: 18,
      borderRadius: 12,
      alignItems: 'center',
    },
    stopButtonText: {
      color: '#fff',
      fontSize: 18,
      fontWeight: '600',
    },
    resumeButton: {
      backgroundColor: theme.colors.primary,
      paddingVertical: 18,
      borderRadius: 12,
      alignItems: 'center',
      marginBottom: 12,
    },
    resumeButtonText: {
      color: '#fff',
      fontSize: 18,
      fontWeight: '600',
    },
    weeklySummary: {
      marginHorizontal: 20,
      marginTop: 32,
    },
    weeklyBar: {
      height: 8,
      backgroundColor: theme.colors.card,
      borderRadius: 4,
      overflow: 'hidden',
    },
    weeklyFill: {
      height: '100%',
      backgroundColor: theme.colors.primary,
      borderRadius: 4,
    },
    weeklyFillOvertime: {
      backgroundColor: theme.colors.danger,
    },
    weeklyText: {
      fontSize: 12,
      color: theme.colors.textMuted,
      marginTop: 8,
      textAlign: 'center',
    },
  });
