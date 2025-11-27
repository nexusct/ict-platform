/**
 * Dashboard Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React, { useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import { useDispatch, useSelector } from 'react-redux';
import { useTheme } from '../context/ThemeContext';
import { useAuth } from '../context/AuthContext';
import { useOffline } from '../context/OfflineContext';
import type { RootState, AppDispatch } from '../store';
import { fetchProjects } from '../store/slices/projectsSlice';
import { fetchTimeEntries } from '../store/slices/timeEntriesSlice';

export const DashboardScreen: React.FC = () => {
  const { theme } = useTheme();
  const { user } = useAuth();
  const { isOnline, queueLength } = useOffline();
  const dispatch = useDispatch<AppDispatch>();
  const navigation = useNavigation();

  const { items: projects, isLoading: projectsLoading } = useSelector(
    (state: RootState) => state.projects
  );
  const { activeEntry, weeklyHours } = useSelector((state: RootState) => state.timeEntries);
  const { unreadCount } = useSelector((state: RootState) => state.notifications);

  const [refreshing, setRefreshing] = React.useState(false);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    await Promise.all([
      dispatch(fetchProjects({ status: 'active' })),
      dispatch(fetchTimeEntries({ start_date: getWeekStart() })),
    ]);
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await loadData();
    setRefreshing(false);
  };

  const getWeekStart = () => {
    const now = new Date();
    const dayOfWeek = now.getDay();
    const diff = now.getDate() - dayOfWeek;
    return new Date(now.setDate(diff)).toISOString().split('T')[0];
  };

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
  };

  const activeProjects = projects.filter((p) => p.status === 'active');
  const urgentProjects = projects.filter((p) => p.priority === 'urgent' || p.priority === 'high');

  const styles = createStyles(theme);

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ScrollView
        style={styles.scrollView}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        {/* Header */}
        <View style={styles.header}>
          <View>
            <Text style={styles.greeting}>{getGreeting()}</Text>
            <Text style={styles.userName}>{user?.name || 'User'}</Text>
          </View>
          <View style={styles.headerRight}>
            {!isOnline && (
              <View style={styles.offlineBadge}>
                <Text style={styles.offlineBadgeText}>Offline</Text>
              </View>
            )}
            {queueLength > 0 && (
              <View style={styles.syncBadge}>
                <Text style={styles.syncBadgeText}>{queueLength} pending</Text>
              </View>
            )}
          </View>
        </View>

        {/* Active Time Entry */}
        {activeEntry && (
          <TouchableOpacity
            style={styles.activeEntry}
            onPress={() => navigation.navigate('TimeTracking' as never)}
          >
            <View style={styles.activeEntryPulse} />
            <View style={styles.activeEntryContent}>
              <Text style={styles.activeEntryLabel}>Currently Tracking</Text>
              <Text style={styles.activeEntryProject}>
                Project #{activeEntry.project_id}
              </Text>
            </View>
            <Text style={styles.activeEntryArrow}>→</Text>
          </TouchableOpacity>
        )}

        {/* Quick Stats */}
        <View style={styles.statsGrid}>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{activeProjects.length}</Text>
            <Text style={styles.statLabel}>Active Projects</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{weeklyHours.toFixed(1)}</Text>
            <Text style={styles.statLabel}>Hours This Week</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={[styles.statValue, urgentProjects.length > 0 && styles.urgentValue]}>
              {urgentProjects.length}
            </Text>
            <Text style={styles.statLabel}>Urgent Tasks</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{unreadCount}</Text>
            <Text style={styles.statLabel}>Notifications</Text>
          </View>
        </View>

        {/* Quick Actions */}
        <Text style={styles.sectionTitle}>Quick Actions</Text>
        <View style={styles.quickActions}>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => navigation.navigate('TimeTracking' as never)}
          >
            <Text style={styles.quickActionIcon}>⏱️</Text>
            <Text style={styles.quickActionLabel}>Clock In</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => navigation.navigate('Projects' as never)}
          >
            <Text style={styles.quickActionIcon}>📁</Text>
            <Text style={styles.quickActionLabel}>Projects</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => navigation.navigate('Inventory' as never)}
          >
            <Text style={styles.quickActionIcon}>📦</Text>
            <Text style={styles.quickActionLabel}>Inventory</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => navigation.navigate('More' as never)}
          >
            <Text style={styles.quickActionIcon}>📄</Text>
            <Text style={styles.quickActionLabel}>Documents</Text>
          </TouchableOpacity>
        </View>

        {/* Recent Projects */}
        <Text style={styles.sectionTitle}>Recent Projects</Text>
        {activeProjects.slice(0, 5).map((project) => (
          <TouchableOpacity
            key={project.id}
            style={styles.projectCard}
            onPress={() =>
              navigation.navigate('Projects', {
                screen: 'ProjectDetail',
                params: { projectId: project.id },
              } as never)
            }
          >
            <View style={styles.projectInfo}>
              <Text style={styles.projectNumber}>{project.project_number}</Text>
              <Text style={styles.projectName}>{project.name}</Text>
              <Text style={styles.projectClient}>{project.client_name}</Text>
            </View>
            <View
              style={[
                styles.projectPriority,
                project.priority === 'urgent' && styles.priorityUrgent,
                project.priority === 'high' && styles.priorityHigh,
              ]}
            >
              <Text style={styles.projectPriorityText}>
                {project.priority.toUpperCase()}
              </Text>
            </View>
          </TouchableOpacity>
        ))}

        <View style={styles.bottomSpacing} />
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
    header: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      paddingHorizontal: 20,
      paddingTop: 20,
      paddingBottom: 16,
    },
    greeting: {
      fontSize: 14,
      color: theme.colors.textSecondary,
    },
    userName: {
      fontSize: 24,
      fontWeight: '700',
      color: theme.colors.text,
    },
    headerRight: {
      flexDirection: 'row',
      gap: 8,
    },
    offlineBadge: {
      backgroundColor: theme.colors.danger,
      paddingHorizontal: 10,
      paddingVertical: 4,
      borderRadius: 12,
    },
    offlineBadgeText: {
      color: '#fff',
      fontSize: 12,
      fontWeight: '600',
    },
    syncBadge: {
      backgroundColor: theme.colors.warning,
      paddingHorizontal: 10,
      paddingVertical: 4,
      borderRadius: 12,
    },
    syncBadgeText: {
      color: '#fff',
      fontSize: 12,
      fontWeight: '600',
    },
    activeEntry: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.colors.success,
      marginHorizontal: 20,
      marginBottom: 20,
      padding: 16,
      borderRadius: 12,
    },
    activeEntryPulse: {
      width: 12,
      height: 12,
      borderRadius: 6,
      backgroundColor: '#fff',
      marginRight: 12,
    },
    activeEntryContent: {
      flex: 1,
    },
    activeEntryLabel: {
      fontSize: 12,
      color: 'rgba(255,255,255,0.8)',
    },
    activeEntryProject: {
      fontSize: 16,
      fontWeight: '600',
      color: '#fff',
    },
    activeEntryArrow: {
      fontSize: 20,
      color: '#fff',
    },
    statsGrid: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      paddingHorizontal: 16,
      gap: 8,
      marginBottom: 24,
    },
    statCard: {
      width: '48%',
      backgroundColor: theme.colors.card,
      padding: 16,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    statValue: {
      fontSize: 28,
      fontWeight: '700',
      color: theme.colors.text,
    },
    urgentValue: {
      color: theme.colors.danger,
    },
    statLabel: {
      fontSize: 13,
      color: theme.colors.textMuted,
      marginTop: 4,
    },
    sectionTitle: {
      fontSize: 18,
      fontWeight: '600',
      color: theme.colors.text,
      paddingHorizontal: 20,
      marginBottom: 12,
    },
    quickActions: {
      flexDirection: 'row',
      paddingHorizontal: 16,
      gap: 8,
      marginBottom: 24,
    },
    quickAction: {
      flex: 1,
      backgroundColor: theme.colors.card,
      padding: 16,
      borderRadius: 12,
      alignItems: 'center',
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    quickActionIcon: {
      fontSize: 24,
      marginBottom: 8,
    },
    quickActionLabel: {
      fontSize: 12,
      color: theme.colors.textSecondary,
      fontWeight: '500',
    },
    projectCard: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.colors.card,
      marginHorizontal: 20,
      marginBottom: 8,
      padding: 16,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    projectInfo: {
      flex: 1,
    },
    projectNumber: {
      fontSize: 12,
      color: theme.colors.textMuted,
      marginBottom: 2,
    },
    projectName: {
      fontSize: 16,
      fontWeight: '600',
      color: theme.colors.text,
    },
    projectClient: {
      fontSize: 13,
      color: theme.colors.textSecondary,
      marginTop: 2,
    },
    projectPriority: {
      backgroundColor: theme.colors.card,
      paddingHorizontal: 10,
      paddingVertical: 4,
      borderRadius: 8,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    priorityUrgent: {
      backgroundColor: 'rgba(239, 68, 68, 0.1)',
      borderColor: theme.colors.danger,
    },
    priorityHigh: {
      backgroundColor: 'rgba(245, 158, 11, 0.1)',
      borderColor: theme.colors.warning,
    },
    projectPriorityText: {
      fontSize: 10,
      fontWeight: '600',
      color: theme.colors.textSecondary,
    },
    bottomSpacing: {
      height: 100,
    },
  });
