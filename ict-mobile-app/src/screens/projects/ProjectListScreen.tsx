/**
 * Project List Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  RefreshControl,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { useDispatch, useSelector } from 'react-redux';
import { useTheme } from '../../context/ThemeContext';
import type { RootState, AppDispatch } from '../../store';
import { fetchProjects, setFilters } from '../../store/slices/projectsSlice';
import type { Project } from '../../types';

export const ProjectListScreen: React.FC = () => {
  const { theme } = useTheme();
  const navigation = useNavigation();
  const dispatch = useDispatch<AppDispatch>();

  const { items, isLoading, filters } = useSelector((state: RootState) => state.projects);
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    dispatch(fetchProjects({}));
  }, [dispatch]);

  const onRefresh = useCallback(() => {
    dispatch(fetchProjects({ status: filters.status !== 'all' ? filters.status : undefined }));
  }, [dispatch, filters.status]);

  const filteredProjects = items.filter((project) => {
    const matchesSearch =
      project.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      project.project_number.toLowerCase().includes(searchQuery.toLowerCase()) ||
      project.client_name.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesStatus = filters.status === 'all' || project.status === filters.status;

    return matchesSearch && matchesStatus;
  });

  const getPriorityColor = (priority: Project['priority']) => {
    const colors = {
      low: theme.colors.textMuted,
      normal: theme.colors.primary,
      high: theme.colors.warning,
      urgent: theme.colors.danger,
    };
    return colors[priority];
  };

  const getStatusColor = (status: Project['status']) => {
    const colors = {
      planning: theme.colors.textMuted,
      active: theme.colors.success,
      on_hold: theme.colors.warning,
      completed: theme.colors.primary,
      cancelled: theme.colors.danger,
    };
    return colors[status];
  };

  const styles = createStyles(theme);

  const renderProject = ({ item }: { item: Project }) => (
    <TouchableOpacity
      style={styles.projectCard}
      onPress={() => navigation.navigate('ProjectDetail', { projectId: item.id } as never)}
    >
      <View style={styles.projectHeader}>
        <Text style={styles.projectNumber}>{item.project_number}</Text>
        <View style={[styles.priorityBadge, { backgroundColor: getPriorityColor(item.priority) }]}>
          <Text style={styles.priorityText}>{item.priority.toUpperCase()}</Text>
        </View>
      </View>
      <Text style={styles.projectName}>{item.name}</Text>
      <Text style={styles.clientName}>{item.client_name}</Text>
      <View style={styles.projectFooter}>
        <View style={[styles.statusBadge, { borderColor: getStatusColor(item.status) }]}>
          <View style={[styles.statusDot, { backgroundColor: getStatusColor(item.status) }]} />
          <Text style={[styles.statusText, { color: getStatusColor(item.status) }]}>
            {item.status.replace('_', ' ')}
          </Text>
        </View>
        {item.end_date && (
          <Text style={styles.dateText}>
            Due: {new Date(item.end_date).toLocaleDateString()}
          </Text>
        )}
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      {/* Search */}
      <View style={styles.searchContainer}>
        <TextInput
          style={styles.searchInput}
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholder="Search projects..."
          placeholderTextColor={theme.colors.textMuted}
        />
      </View>

      {/* Filters */}
      <View style={styles.filterContainer}>
        {['all', 'active', 'planning', 'completed'].map((status) => (
          <TouchableOpacity
            key={status}
            style={[styles.filterChip, filters.status === status && styles.filterChipActive]}
            onPress={() => dispatch(setFilters({ status }))}
          >
            <Text
              style={[
                styles.filterChipText,
                filters.status === status && styles.filterChipTextActive,
              ]}
            >
              {status.charAt(0).toUpperCase() + status.slice(1)}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* List */}
      <FlatList
        data={filteredProjects}
        renderItem={renderProject}
        keyExtractor={(item) => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={isLoading} onRefresh={onRefresh} />}
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyText}>No projects found</Text>
          </View>
        }
      />
    </View>
  );
};

const createStyles = (theme: any) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.colors.background,
    },
    searchContainer: {
      paddingHorizontal: 16,
      paddingVertical: 12,
    },
    searchInput: {
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: 10,
      paddingHorizontal: 16,
      paddingVertical: 12,
      fontSize: 15,
      color: theme.colors.text,
    },
    filterContainer: {
      flexDirection: 'row',
      paddingHorizontal: 16,
      paddingBottom: 12,
      gap: 8,
    },
    filterChip: {
      paddingHorizontal: 14,
      paddingVertical: 8,
      borderRadius: 20,
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    filterChipActive: {
      backgroundColor: theme.colors.primary,
      borderColor: theme.colors.primary,
    },
    filterChipText: {
      fontSize: 13,
      color: theme.colors.textSecondary,
    },
    filterChipTextActive: {
      color: '#fff',
      fontWeight: '600',
    },
    listContent: {
      padding: 16,
      paddingTop: 0,
    },
    projectCard: {
      backgroundColor: theme.colors.card,
      borderRadius: 12,
      padding: 16,
      marginBottom: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    projectHeader: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      marginBottom: 8,
    },
    projectNumber: {
      fontSize: 12,
      color: theme.colors.textMuted,
      fontFamily: 'monospace',
    },
    priorityBadge: {
      paddingHorizontal: 8,
      paddingVertical: 3,
      borderRadius: 4,
    },
    priorityText: {
      fontSize: 10,
      fontWeight: '700',
      color: '#fff',
    },
    projectName: {
      fontSize: 17,
      fontWeight: '600',
      color: theme.colors.text,
      marginBottom: 4,
    },
    clientName: {
      fontSize: 14,
      color: theme.colors.textSecondary,
      marginBottom: 12,
    },
    projectFooter: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
    },
    statusBadge: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: 10,
      paddingVertical: 4,
      borderRadius: 12,
      borderWidth: 1,
    },
    statusDot: {
      width: 6,
      height: 6,
      borderRadius: 3,
      marginRight: 6,
    },
    statusText: {
      fontSize: 12,
      fontWeight: '500',
      textTransform: 'capitalize',
    },
    dateText: {
      fontSize: 12,
      color: theme.colors.textMuted,
    },
    emptyContainer: {
      alignItems: 'center',
      paddingVertical: 40,
    },
    emptyText: {
      fontSize: 15,
      color: theme.colors.textMuted,
    },
  });
