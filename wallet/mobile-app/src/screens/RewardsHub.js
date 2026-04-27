import React, { useCallback, useState } from 'react';
import {
  ScrollView,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import { SafeAreaView } from 'react-native-safe-area-context';
import ScreenHeader from '../components/ScreenHeader';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import { claimRewardMission, describeReward, fetchRewardsMissions } from '../services/rewards';

export default function RewardsHub({ navigation }) {
  const { refreshWallet } = useAuth();
  const { alert } = useDialog();
  const [payload, setPayload] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [claimingId, setClaimingId] = useState(null);

  const load = useCallback(async () => {
    try {
      const data = await fetchRewardsMissions();
      setPayload(data);
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const handleClaim = useCallback(async (mission) => {
    if (!mission?.id || claimingId) return;
    setClaimingId(mission.id);
    try {
      const result = await claimRewardMission(mission.id);
      await Promise.all([refreshWallet(), load()]);
      await alert({
        title: 'Reward claimed',
        message: `You earned ${describeReward({
          type: result.reward?.reward_type,
          value: result.reward?.reward_value,
          meta: result.reward?.meta,
        })}.`,
        tone: 'success',
      });
    } catch (err) {
      const message = err?.response?.data?.message || 'Unable to claim this reward right now.';
      await alert({ title: 'Claim failed', message, tone: 'danger' });
    } finally {
      setClaimingId(null);
    }
  }, [alert, claimingId, load, refreshWallet]);

  const streak = payload?.streak || {};
  const missions = payload?.missions || [];
  const recentRewards = payload?.recent_rewards || [];
  const badges = payload?.badges || [];
  const completedCount = missions.filter(item => item.is_completed).length;
  const claimableCount = missions.filter(item => item.is_completed && !item.is_claimed).length;

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ScreenHeader title="Rewards Hub" onLeftPress={() => navigation.goBack()} />

      {loading ? (
        <ActivityIndicator color="#111827" style={{ marginTop: 40 }} />
      ) : (
        <ScrollView
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        >
          <View style={styles.heroCard}>
            <Text style={styles.heroEyebrow}>Current streak</Text>
            <Text style={styles.heroTitle}>Day {Number(streak.current_count || 0)}</Text>
            <Text style={styles.heroSub}>
              Longest streak: {Number(streak.longest_count || 0)} days
            </Text>
            <View style={styles.heroStatsRow}>
              <View style={styles.heroStatCard}>
                <Text style={styles.heroStatValue}>{completedCount}</Text>
                <Text style={styles.heroStatLabel}>Done today</Text>
              </View>
              <View style={styles.heroStatCard}>
                <Text style={styles.heroStatValue}>{claimableCount}</Text>
                <Text style={styles.heroStatLabel}>Ready to claim</Text>
              </View>
            </View>
          </View>

          <Text style={styles.sectionTitle}>Today&apos;s Missions</Text>
          <View style={styles.card}>
            {missions.length === 0 ? (
              <View style={styles.emptyState}>
                <Feather name="target" size={28} color="#D1D5DB" />
                <Text style={styles.emptyTitle}>No missions yet</Text>
                <Text style={styles.emptySub}>Your missions will appear here after refresh.</Text>
              </View>
            ) : missions.map((mission, index) => {
              const progress = Number(mission.progress || 0);
              const target = Math.max(1, Number(mission.target_count || 1));
              const pct = Math.min(100, Math.round((progress / target) * 100));
              const isClaimable = mission.is_completed && !mission.is_claimed;

              return (
                <View
                  key={mission.id}
                  style={[styles.missionCard, index === missions.length - 1 && { marginBottom: 0 }]}
                >
                  <View style={styles.missionTop}>
                    <View style={styles.missionIcon}>
                      <Feather
                        name={mission.is_claimed ? 'check-circle' : mission.is_completed ? 'award' : 'zap'}
                        size={16}
                        color={mission.is_claimed ? '#047857' : '#111827'}
                      />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.missionTitle}>{mission.title}</Text>
                      <Text style={styles.missionDesc}>{mission.description}</Text>
                    </View>
                  </View>

                  <View style={styles.progressTrack}>
                    <View style={[styles.progressFill, { width: `${pct}%` }]} />
                  </View>

                  <View style={styles.missionBottom}>
                    <Text style={styles.missionMeta}>
                      {progress}/{target} complete · {describeReward(mission.reward)}
                    </Text>
                    {isClaimable ? (
                      <TouchableOpacity
                        style={styles.claimBtn}
                        activeOpacity={0.85}
                        disabled={claimingId === mission.id}
                        onPress={() => handleClaim(mission)}
                      >
                        {claimingId === mission.id ? (
                          <ActivityIndicator size="small" color="#fff" />
                        ) : (
                          <Text style={styles.claimBtnText}>Claim</Text>
                        )}
                      </TouchableOpacity>
                    ) : (
                      <Text style={styles.missionState}>
                        {mission.is_claimed ? 'Claimed' : mission.is_completed ? 'Ready' : 'In progress'}
                      </Text>
                    )}
                  </View>
                </View>
              );
            })}
          </View>

          <Text style={styles.sectionTitle}>Recent Rewards</Text>
          <View style={styles.card}>
            {recentRewards.length === 0 ? (
              <View style={styles.emptyState}>
                <Feather name="gift" size={28} color="#D1D5DB" />
                <Text style={styles.emptyTitle}>Nothing claimed yet</Text>
                <Text style={styles.emptySub}>Claimed rewards will show up here.</Text>
              </View>
            ) : recentRewards.map((reward, index) => (
              <View key={reward.id} style={[styles.rewardRow, index === recentRewards.length - 1 && styles.rewardRowLast]}>
                <View style={[styles.rewardChip, reward.reward_type === 'badge_unlock' && styles.rewardChipBadge]}>
                  <Feather name={reward.reward_type === 'badge_unlock' ? 'shield' : 'gift'} size={14} color="#111827" />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.rewardTitle}>
                    {describeReward({
                      type: reward.reward_type,
                      value: reward.reward_value,
                      meta: reward.meta,
                    })}
                  </Text>
                  <Text style={styles.rewardSub}>
                    {reward.created_at ? new Date(reward.created_at).toLocaleString() : 'Just now'}
                  </Text>
                </View>
              </View>
            ))}
          </View>

          <Text style={styles.sectionTitle}>Badges</Text>
          <View style={styles.card}>
            {badges.length === 0 ? (
              <View style={styles.emptyState}>
                <Feather name="shield" size={28} color="#D1D5DB" />
                <Text style={styles.emptyTitle}>No badges yet</Text>
                <Text style={styles.emptySub}>Complete missions that unlock badges to collect them here.</Text>
              </View>
            ) : (
              <View style={styles.badgesWrap}>
                {badges.map((badge) => (
                  <View key={badge.id} style={styles.badgePill}>
                    <Feather name="award" size={14} color="#047857" />
                    <Text style={styles.badgePillText}>{badge.title || badge.code}</Text>
                  </View>
                ))}
              </View>
            )}
          </View>
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F3F4F6' },
  heroCard: {
    backgroundColor: '#111827',
    borderRadius: 18,
    padding: 18,
  },
  heroEyebrow: { fontSize: 11, fontWeight: '800', color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', letterSpacing: 0.8 },
  heroTitle: { marginTop: 6, fontSize: 28, fontWeight: '900', color: '#fff' },
  heroSub: { marginTop: 4, fontSize: 13, color: 'rgba(255,255,255,0.7)' },
  heroStatsRow: { flexDirection: 'row', gap: 10, marginTop: 16 },
  heroStatCard: {
    flex: 1,
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderRadius: 14,
    padding: 12,
  },
  heroStatValue: { fontSize: 20, fontWeight: '900', color: '#fff' },
  heroStatLabel: { marginTop: 4, fontSize: 11, color: 'rgba(255,255,255,0.65)' },
  sectionTitle: { fontSize: 13, fontWeight: '800', color: '#6B7280', marginTop: 20, marginBottom: 8 },
  card: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
  },
  missionCard: {
    backgroundColor: '#F9FAFB',
    borderRadius: 12,
    padding: 12,
    marginBottom: 10,
  },
  missionTop: { flexDirection: 'row', gap: 10, alignItems: 'center' },
  missionIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    alignItems: 'center',
    justifyContent: 'center',
  },
  missionTitle: { fontSize: 14, fontWeight: '800', color: '#111827' },
  missionDesc: { marginTop: 2, fontSize: 12, color: '#6B7280' },
  progressTrack: {
    marginTop: 12,
    height: 8,
    borderRadius: 999,
    backgroundColor: '#E5E7EB',
    overflow: 'hidden',
  },
  progressFill: { height: '100%', backgroundColor: '#111827', borderRadius: 999 },
  missionBottom: {
    marginTop: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  missionMeta: { flex: 1, fontSize: 11, color: '#6B7280' },
  claimBtn: {
    backgroundColor: '#111827',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
    minWidth: 76,
    alignItems: 'center',
  },
  claimBtnText: { color: '#fff', fontSize: 12, fontWeight: '800' },
  missionState: { fontSize: 11, fontWeight: '800', color: '#6B7280' },
  rewardRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingBottom: 12,
    marginBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  rewardRowLast: { marginBottom: 0, paddingBottom: 0, borderBottomWidth: 0 },
  rewardChip: {
    width: 34,
    height: 34,
    borderRadius: 10,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  rewardChipBadge: { backgroundColor: '#ECFDF5' },
  rewardTitle: { fontSize: 13, fontWeight: '800', color: '#111827' },
  rewardSub: { marginTop: 2, fontSize: 11, color: '#6B7280' },
  badgesWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  badgePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#ECFDF5',
    borderWidth: 1,
    borderColor: '#A7F3D0',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  badgePillText: { fontSize: 12, fontWeight: '800', color: '#065F46' },
  emptyState: { alignItems: 'center', paddingVertical: 20, gap: 6 },
  emptyTitle: { fontSize: 14, fontWeight: '800', color: '#374151' },
  emptySub: { fontSize: 12, color: '#9CA3AF', textAlign: 'center', lineHeight: 18 },
});
