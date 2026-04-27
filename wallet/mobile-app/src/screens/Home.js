import React, { useEffect, useState, useCallback, useRef } from 'react';
import { ScrollView, View, Text, TouchableOpacity, StyleSheet, RefreshControl, ActivityIndicator, Image, PanResponder } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useFocusEffect, DrawerActions } from '@react-navigation/native';
import * as Location from 'expo-location';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useDialog } from '../context/DialogContext';
import WalletCard from '../components/WalletCard';
import api from '../services/client';
import { checkInRewards, claimRewardMission, describeReward, fetchRewardsSummary } from '../services/rewards';

const REWARDS_CHECKIN_KEY = 'extracash_rewards_last_checkin_date_v1';

export default function Home({ navigation }) {
  const { user, wallet, refreshWallet } = useAuth();
  const { itemCount: cartCount, refresh: refreshCart } = useCart();
  const { alert } = useDialog();
  const [refreshing, setRefreshing] = useState(false);
  const [walletMatch, setWalletMatch] = useState(null);
  const [loadingMatch, setLoadingMatch] = useState(true);
  const [rewardsSummary, setRewardsSummary] = useState(null);
  const [loadingRewards, setLoadingRewards] = useState(true);
  const [rewardsError, setRewardsError] = useState(null);
  const [claimingMissionId, setClaimingMissionId] = useState(null);
  const checkInInFlight = useRef(false);

  const getLocationCoords = async () => {
    try {
      const { status } = await Location.getForegroundPermissionsAsync();
      if (status === 'granted') {
        const loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Low });
        return { lat: loc.coords.latitude, lng: loc.coords.longitude };
      }
    } catch {}
    return { lat: -15.4166, lng: 28.2833 };
  };

  const fetchWalletMatch = useCallback(async (balance) => {
    setLoadingMatch(true);
    try {
      const coords = await getLocationCoords();
      const res = await api.get('/products/nearby', {
        params: { lat: coords.lat, lng: coords.lng, radius: 50, per_page: 40, page: 1 },
      });
      const items = res.data.data || res.data || [];
      if (!items.length) {
        setWalletMatch(null);
        return;
      }
      const budget = Number(balance || 0);
      const affordable = items.filter(p => Number(p.price) > 0 && Number(p.price) <= budget);
      const pool = affordable.length > 0 ? affordable : items;
      const pick = pool[Math.floor(Math.random() * pool.length)];
      setWalletMatch(pick);
    } catch {
      setWalletMatch(null);
    } finally {
      setLoadingMatch(false);
    }
  }, []);

  const loadRewardsSummary = useCallback(async (options = {}) => {
    const { runCheckIn = false } = options;
    setRewardsError(null);
    try {
      // Avoid hitting the check-in throttle by only calling check-in once per day.
      const today = new Date().toISOString().slice(0, 10);
      const lastCheckIn = await AsyncStorage.getItem(REWARDS_CHECKIN_KEY);

      let payload;
      if (runCheckIn && lastCheckIn !== today && !checkInInFlight.current) {
        checkInInFlight.current = true;
        try {
          payload = await checkInRewards();
          await AsyncStorage.setItem(REWARDS_CHECKIN_KEY, today);
        } finally {
          checkInInFlight.current = false;
        }
      } else {
        payload = await fetchRewardsSummary();
      }
      setRewardsSummary(payload.summary || payload);
    } catch (err) {
      const status = err?.response?.status;
      if (status === 429) {
        // Don't spam the UI with a flashing throttle message.
        setRewardsError('Rewards is busy. Pull to refresh in a moment.');
      } else {
        const msg = err?.response?.data?.message || (err?.request ? 'Network error — check connection and API URL.' : null);
        setRewardsError(msg || 'Could not load rewards. Pull to refresh.');
      }
      if (!runCheckIn) setRewardsSummary(null);
    } finally {
      setLoadingRewards(false);
    }
  }, []);

  const handleClaimMission = useCallback(async (mission) => {
    if (!mission?.id || claimingMissionId) return;
    setClaimingMissionId(mission.id);
    try {
      const result = await claimRewardMission(mission.id);
      setRewardsSummary(result.summary || null);
      await refreshWallet();
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
      setClaimingMissionId(null);
    }
  }, [alert, claimingMissionId, refreshWallet]);

  useEffect(() => { fetchWalletMatch(wallet?.balance); }, [wallet?.balance, fetchWalletMatch]);

  useFocusEffect(
    useCallback(() => {
      loadRewardsSummary({ runCheckIn: true });
      refreshWallet();
      refreshCart();
    }, [loadRewardsSummary, refreshWallet, refreshCart])
  );

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.all([
      refreshWallet(),
      fetchWalletMatch(wallet?.balance),
      refreshCart(),
      loadRewardsSummary({ runCheckIn: false }),
    ]);
    setRefreshing(false);
  }, [fetchWalletMatch, wallet?.balance, refreshCart, refreshWallet, loadRewardsSummary]);

  const initials = user?.name?.split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase() || 'U';
  const walletBalance = Number(wallet?.balance || 0);
  const hasLowBalance = walletBalance < 100;
  const streakCount = Number(rewardsSummary?.streak?.current_count || 0);
  const missionBlock = rewardsSummary?.missions;
  const rewardMissions = Array.isArray(missionBlock?.missions) ? missionBlock.missions : [];
  const missionsDone = Math.max(0, Number(missionBlock?.completed_missions ?? 0));
  const missionsTotal = Math.max(0, Number(missionBlock?.total_missions ?? 0));
  const claimableCount = Math.max(0, Number(missionBlock?.claimable_missions ?? 0));
  const recentRewards = rewardsSummary?.recent_rewards || [];
  const streakProgram = rewardsSummary?.streak_program || [];

  const recommendationCandidates = [
    {
      key: 'low-balance',
      score: hasLowBalance ? 100 : 0,
      title: 'Top up to avoid missed earning opportunities',
      message: 'Your wallet is low. Add funds now so you can keep buying cashback items and rotate faster.',
      cta: 'Top Up',
      screen: 'Fund',
      tone: 'warn',
    },
    {
      key: 'growth',
      score: 40,
      title: 'Reinvest with nearby cashback deals',
      message: 'Your core loop is healthy. Find high-demand products nearby and compound earnings with cashback.',
      cta: 'Find Deals',
      screen: 'NearbyProducts',
      tone: 'success',
    },
  ];

  const smartRecommendation = recommendationCandidates.sort((a, b) => b.score - a.score)[0];
  const smartCardToneStyle = smartRecommendation.tone === 'danger'
    ? styles.strategyCardDanger
    : smartRecommendation.tone === 'warn'
      ? styles.strategyCardWarn
      : null;
  const smartTitleToneStyle = smartRecommendation.tone === 'danger'
    ? styles.strategyTitleDanger
    : smartRecommendation.tone === 'warn'
      ? styles.strategyTitleWarn
      : null;
  const smartTextToneStyle = smartRecommendation.tone === 'danger'
    ? styles.strategyTextDanger
    : smartRecommendation.tone === 'warn'
      ? styles.strategyTextWarn
      : null;
  const smartBtnToneStyle = smartRecommendation.tone === 'danger'
    ? styles.strategyBtnDanger
    : smartRecommendation.tone === 'warn'
      ? styles.strategyBtnWarn
      : null;

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: '#F3F4F6' }} edges={['top']}>
      {/* Top bar */}
      <View style={[styles.topBar, { paddingTop: 12 }]}>
        <View style={styles.logoRow}>
          <TouchableOpacity
            style={styles.menuBtn}
            onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
            hitSlop={{ top: 12, bottom: 12, left: 8, right: 8 }}
            activeOpacity={0.65}
            accessibilityLabel="Open menu"
          >
            <Feather name="more-vertical" size={22} color="#111827" />
          </TouchableOpacity>
          <Text style={styles.logoText}>ExtraCash</Text>
        </View>
        <View style={styles.topActions}>
          <TouchableOpacity
            style={styles.iconCircle}
            onPress={() => navigation.navigate('Cart')}
            activeOpacity={0.8}
          >
            <Feather name="shopping-cart" size={18} color="#111827" />
            {cartCount > 0 ? (
              <View style={styles.cartBadge}>
                <Text style={styles.cartBadgeText}>{cartCount > 9 ? '9+' : cartCount}</Text>
              </View>
            ) : null}
          </TouchableOpacity>
          <TouchableOpacity style={styles.iconCircle}>
            <Feather name="bell" size={18} color="#111827" />
          </TouchableOpacity>
          <TouchableOpacity style={styles.avatar} onPress={() => navigation.navigate('Profile')}>
            <Text style={styles.avatarText}>{initials}</Text>
          </TouchableOpacity>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        {user?.has_open_fraud_review ? (
          <View style={styles.fraudBanner}>
            <Feather name="alert-triangle" size={18} color="#92400E" style={{ marginTop: 2 }} />
            <Text style={styles.fraudBannerText}>
              Your account has a security review in progress. If you need help, contact support.
            </Text>
          </View>
        ) : null}
        <WalletCard
          name={user?.name || ''}
          balance={wallet?.balance || 0}
          currency={wallet?.currency || 'ZMW'}
          cardNumber={wallet?.card_number || ''}
          expiry={wallet?.expiry || ''}
          onPress={() => navigation.navigate('Wallet')}
        />

        {/* ── Marketplace chevron panels ── */}
        <View style={styles.marketRow}>
          {/* LEFT — My Listings */}
          <TouchableOpacity
            style={[styles.chevronPanel, styles.chevronLeft, styles.marketLeftCard]}
            onPress={() => navigation.navigate('MyProducts')}
            activeOpacity={0.8}
          >
            <Feather name="chevron-left" size={22} color="#fff" style={{ marginRight: 4 }} />
            <View style={{ flex: 1 }}>
              <Text style={styles.chevronLabel}>My Listings</Text>
              <Text style={styles.chevronSub}>Sell your products</Text>
            </View>
            <Feather name="shopping-bag" size={18} color="rgba(255,255,255,0.5)" />
          </TouchableOpacity>

          {/* RIGHT — Nearby Products */}
          <TouchableOpacity
            style={[styles.chevronPanel, styles.chevronRight]}
            onPress={() => navigation.navigate('NearbyProducts')}
            activeOpacity={0.8}
          >
            <Feather name="map-pin" size={18} color="rgba(0,0,0,0.4)" />
            <View style={{ flex: 1, alignItems: 'flex-end' }}>
              <Text style={[styles.chevronLabel, { color: '#111', textAlign: 'right' }]}>Near Me</Text>
              <Text style={[styles.chevronSub, { color: '#6B7280', textAlign: 'right' }]}>Shop &amp; earn cashback</Text>
            </View>
            <Feather name="chevron-right" size={22} color="#111" style={{ marginLeft: 4 }} />
          </TouchableOpacity>
        </View>

        {/* Quick Actions Grid */}
        <Text style={styles.sectionTitle}>Quick Actions</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.quickActionsContainer}>
          <TouchableOpacity style={styles.quickActionCard} onPress={() => navigation.navigate('Fund')} activeOpacity={0.8}>
            <View style={[styles.actionIcon, { backgroundColor: '#EFF6FF' }]}>
              <Feather name="plus-circle" size={20} color="#2563EB" />
            </View>
            <Text style={styles.actionLabel}>Top Up</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.quickActionCard} onPress={() => navigation.navigate('Withdraw')} activeOpacity={0.8}>
            <View style={[styles.actionIcon, { backgroundColor: '#FEF2F2' }]}>
              <Feather name="minus-circle" size={20} color="#DC2626" />
            </View>
            <Text style={styles.actionLabel}>Withdraw</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.quickActionCard} onPress={() => navigation.navigate('Send')} activeOpacity={0.8}>
            <View style={[styles.actionIcon, { backgroundColor: '#ECFDF3' }]}>
              <Feather name="send" size={20} color="#059669" />
            </View>
            <Text style={styles.actionLabel}>Send</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.quickActionCard} onPress={() => navigation.navigate('RequestMoney')} activeOpacity={0.8}>
            <View style={[styles.actionIcon, { backgroundColor: '#FEF3C7' }]}>
              <Feather name="dollar-sign" size={20} color="#D97706" />
            </View>
            <Text style={styles.actionLabel}>Request</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.quickActionCard} onPress={() => {}} activeOpacity={0.8}>
            <View style={[styles.actionIcon, { backgroundColor: '#F0FDF4' }]}>
              <Feather name="phone" size={20} color="#16A34A" />
            </View>
            <Text style={styles.actionLabel}>Airtime</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.quickActionCard} onPress={() => {}} activeOpacity={0.8}>
            <View style={[styles.actionIcon, { backgroundColor: '#FFFBEB' }]}>
              <Feather name="wifi" size={20} color="#CA8A04" />
            </View>
            <Text style={styles.actionLabel}>Data</Text>
          </TouchableOpacity>
        </ScrollView>

        {/* Wallet Match — suggested nearby product around your balance */}
        <Text style={styles.sectionTitle}>Match for your wallet</Text>
        <Text style={styles.matchHint}>
          A nearby vendor pick{walletBalance > 0 ? ` under ZMW ${walletBalance.toFixed(2)}` : ''}
        </Text>
        {loadingMatch ? (
          <View style={styles.matchLoading}>
            <ActivityIndicator color="#000" />
          </View>
        ) : walletMatch ? (
          <TouchableOpacity
            style={styles.matchCard}
            activeOpacity={0.85}
            onPress={() => navigation.navigate('ProductDetail', { product: walletMatch, productId: walletMatch.id })}
          >
            <View style={styles.matchImageBox}>
              {walletMatch.image_url ? (
                <Image
                  source={{ uri: walletMatch.image_url }}
                  style={{ width: '100%', height: '100%', borderRadius: 10 }}
                  resizeMode="cover"
                />
              ) : (
                <Feather name="package" size={32} color="#D1D5DB" />
              )}
            </View>
            <View style={styles.matchBody}>
              <Text style={styles.matchCategory}>{walletMatch.category || 'Featured'}</Text>
              <Text style={styles.matchTitle} numberOfLines={2}>{walletMatch.title}</Text>
              <View style={styles.matchMetaRow}>
                <Feather name="user" size={11} color="#9CA3AF" />
                <Text style={styles.matchMetaText} numberOfLines={1}>
                  {walletMatch.seller?.name || 'Vendor'}
                </Text>
              </View>
              <View style={styles.matchMetaRow}>
                <Feather name="map-pin" size={11} color="#9CA3AF" />
                <Text style={styles.matchMetaText} numberOfLines={1}>
                  {walletMatch.location_label || 'Nearby'}
                  {walletMatch.distance_km != null ? ` · ${parseFloat(walletMatch.distance_km).toFixed(1)} km` : ''}
                </Text>
              </View>
              <View style={styles.matchPriceRow}>
                <Text style={styles.matchPrice}>ZMW {parseFloat(walletMatch.price).toFixed(2)}</Text>
                <View style={styles.matchCashbackPill}>
                  <Feather name="gift" size={10} color="#fff" />
                  <Text style={styles.matchCashbackText}>
                    +ZMW {parseFloat(walletMatch.cashback_amount || 0).toFixed(2)}
                  </Text>
                </View>
              </View>
            </View>
          </TouchableOpacity>
        ) : (
          <TouchableOpacity
            style={styles.matchEmpty}
            activeOpacity={0.85}
            onPress={() => navigation.navigate('NearbyProducts')}
          >
            <Feather name="shopping-bag" size={22} color="#6B7280" />
            <View style={{ flex: 1 }}>
              <Text style={styles.matchEmptyTitle}>No nearby match yet</Text>
              <Text style={styles.matchEmptySub}>Browse all products near you</Text>
            </View>
            <Feather name="chevron-right" size={20} color="#6B7280" />
          </TouchableOpacity>
        )}

        <View style={styles.rewardsCard}>
          <View style={styles.rewardsHeader}>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={styles.rewardsEyebrow}>Streaks & Missions</Text>
              <Text style={styles.rewardsTitle}>Day {streakCount} streak</Text>
              <Text style={styles.rewardsSub}>
                {missionsTotal > 0
                  ? `${missionsDone} of ${missionsTotal} missions complete`
                  : loadingRewards
                    ? 'Syncing…'
                    : rewardsError
                      ? 'Could not load missions'
                      : 'Missions will show after check-in with the server'}
              </Text>
            </View>
            <TouchableOpacity
              style={styles.rewardsHubBtn}
              activeOpacity={0.85}
              onPress={() => navigation.navigate('RewardsHub')}
            >
              <Feather name="gift" size={14} color="#111827" />
              <Text style={styles.rewardsHubBtnText}>Open</Text>
            </TouchableOpacity>
          </View>

          {loadingRewards ? (
            <View style={styles.rewardsLoading}>
              <ActivityIndicator color="#111827" />
            </View>
          ) : (
            <>
              {rewardsError ? (
                <Text style={styles.rewardsErrorText} numberOfLines={2}>
                  {rewardsError}
                </Text>
              ) : null}

              {streakProgram.length > 0 ? (
                <View style={styles.streakProgramBlock}>
                  <Text style={styles.streakProgramLabel}>Streak day bonuses</Text>
                  <ScrollView
                    horizontal
                    showsHorizontalScrollIndicator={false}
                    contentContainerStyle={styles.streakChipsRow}
                  >
                    {streakProgram.map((row) => (
                      <View key={row.code} style={styles.streakChip}>
                        <Text style={styles.streakChipTitle}>Day {row.day_number}</Text>
                        <Text style={styles.streakChipValue} numberOfLines={1}>
                          {describeReward({
                            type: row.reward_type,
                            value: row.reward_value,
                            meta: { label: row.title },
                          })}
                        </Text>
                      </View>
                    ))}
                  </ScrollView>
                </View>
              ) : null}

              {recentRewards.length > 0 ? (
                <View style={styles.recentRewardsBlock}>
                  <Text style={styles.recentRewardsLabel}>Recent rewards</Text>
                  {recentRewards.slice(0, 3).map((g) => (
                    <View key={g.id} style={styles.recentRewardRow}>
                      <Feather name="gift" size={14} color="#047857" />
                      <Text style={styles.recentRewardText} numberOfLines={1}>
                        {describeReward({
                          type: g.reward_type,
                          value: g.reward_value,
                          meta: g.meta,
                        })}
                      </Text>
                    </View>
                  ))}
                </View>
              ) : null}

              {claimableCount > 0 ? (
                <View style={styles.claimReadyPill}>
                  <Feather name="award" size={12} color="#065F46" />
                  <Text style={styles.claimReadyText}>
                    {claimableCount} reward{claimableCount === 1 ? '' : 's'} ready to claim
                  </Text>
                </View>
              ) : (
                <View style={styles.claimReadyPillMuted}>
                  <Feather name="calendar" size={12} color="#6B7280" />
                  <Text style={styles.claimReadyTextMuted}>Daily check-in keeps the streak alive</Text>
                </View>
              )}

              <View style={styles.missionList}>
                {rewardMissions.length === 0 ? (
                  <View style={styles.missionEmpty}>
                    <Text style={styles.missionEmptyText}>
                      {rewardsError
                        ? 'Could not load missions. Pull to refresh, or check your network and API settings.'
                        : 'No mission list yet. Pull to refresh, or tap Open to see the full rewards hub.'}
                    </Text>
                  </View>
                ) : null}
                {rewardMissions.slice(0, 3).map((mission) => {
                  const progress = Number(mission.progress || 0);
                  const target = Number(mission.target_count || 1);
                  const isClaimable = mission.is_completed && !mission.is_claimed;
                  return (
                    <View key={mission.id} style={styles.missionRow}>
                      <View style={styles.missionIcon}>
                        <Feather
                          name={mission.is_claimed ? 'check-circle' : mission.is_completed ? 'award' : 'target'}
                          size={15}
                          color={mission.is_claimed ? '#047857' : '#111827'}
                        />
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.missionTitle}>{mission.title}</Text>
                        <Text style={styles.missionSub}>
                          {progress}/{target} · {describeReward(mission.reward)}
                        </Text>
                      </View>
                      {isClaimable ? (
                        <TouchableOpacity
                          style={styles.missionClaimBtn}
                          activeOpacity={0.85}
                          disabled={claimingMissionId === mission.id}
                          onPress={() => handleClaimMission(mission)}
                        >
                          {claimingMissionId === mission.id ? (
                            <ActivityIndicator size="small" color="#fff" />
                          ) : (
                            <Text style={styles.missionClaimText}>Claim</Text>
                          )}
                        </TouchableOpacity>
                      ) : (
                        <Text style={styles.missionState}>
                          {mission.is_claimed ? 'Claimed' : mission.is_completed ? 'Ready' : 'In progress'}
                        </Text>
                      )}
                    </View>
                  );
                })}
              </View>
            </>
          )}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  topBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  logoRow: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  menuBtn: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 4,
  },
  logoText: { fontSize: 26, fontWeight: '800', marginLeft: 4, letterSpacing: -0.4, color: '#111827' },
  topActions: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  iconCircle: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center', position: 'relative' },
  cartBadge: {
    position: 'absolute',
    top: -3,
    right: -3,
    minWidth: 18,
    height: 18,
    paddingHorizontal: 4,
    borderRadius: 9,
    backgroundColor: '#DC2626',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: '#fff',
  },
  cartBadgeText: { color: '#fff', fontSize: 9, fontWeight: '800' },
  avatar: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#000', justifyContent: 'center', alignItems: 'center' },
  avatarText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  // Marketplace chevron panels
  marketRow: { flexDirection: 'row', marginTop: 12, marginBottom: 4 },
  marketLeftCard: { marginRight: 8 },
  chevronPanel: { flex: 1, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 14, borderRadius: 12, gap: 6 },
  chevronLeft:  { backgroundColor: '#111827' },
  chevronRight: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#E5E7EB' },
  chevronLabel: { fontSize: 13, fontWeight: '800', color: '#fff' },
  chevronSub:   { fontSize: 10, color: 'rgba(255,255,255,0.6)', marginTop: 1 },

  insightRow: { flexDirection: 'row', gap: 10 },
  insightCard: {
    flex: 1,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  insightLabel: { fontSize: 11, color: '#6B7280', fontWeight: '700' },
  insightValue: { marginTop: 4, fontSize: 16, color: '#111827', fontWeight: '800' },
  insightHint: { marginTop: 4, fontSize: 10, color: '#9CA3AF' },
  strategyCard: {
    marginTop: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    borderRadius: 12,
    backgroundColor: '#ECFDF3',
    borderWidth: 1,
    borderColor: '#BBF7D0',
    flexDirection: 'row',
    gap: 10,
    alignItems: 'center',
  },
  strategyCardWarn: { backgroundColor: '#FFFBEB', borderColor: '#FCD34D' },
  strategyCardDanger: { backgroundColor: '#FEF2F2', borderColor: '#FECACA' },
  strategyTitle: { fontSize: 12, fontWeight: '800', color: '#065F46' },
  strategyTitleWarn: { color: '#92400E' },
  strategyTitleDanger: { color: '#991B1B' },
  strategyText: { marginTop: 4, fontSize: 11, lineHeight: 16, color: '#047857' },
  strategyTextWarn: { color: '#B45309' },
  strategyTextDanger: { color: '#B91C1C' },
  strategyBtn: {
    backgroundColor: '#047857',
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  strategyBtnWarn: { backgroundColor: '#B45309' },
  strategyBtnDanger: { backgroundColor: '#B91C1C' },
  strategyBtnText: { color: '#fff', fontSize: 11, fontWeight: '800' },

  sectionTitle: { fontSize: 14, fontWeight: '800', color: '#6B7280', marginTop: 20, marginBottom: 8 },

  quickActionsContainer: { paddingRight: 16, gap: 10 },
  quickActionCard: {
    alignItems: 'center',
    gap: 6,
    width: 78,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  actionIcon: {
    width: 40,
    height: 40,
    borderRadius: 10,
    justifyContent: 'center',
    alignItems: 'center',
  },
  actionLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#374151',
    textAlign: 'center',
  },

  rewardsCard: {
    marginTop: 12,
    backgroundColor: '#fff',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
    marginBottom: 4,
  },
  rewardsHeader: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 },
  rewardsEyebrow: { fontSize: 11, fontWeight: '800', color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.6 },
  rewardsTitle: { marginTop: 4, fontSize: 18, fontWeight: '900', color: '#111827' },
  rewardsSub: { marginTop: 3, fontSize: 12, color: '#6B7280', fontWeight: '600' },
  rewardsHubBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  rewardsHubBtnText: { fontSize: 12, fontWeight: '800', color: '#111827' },
  rewardsLoading: { paddingVertical: 24, alignItems: 'center' },
  claimReadyPill: {
    marginTop: 12,
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#ECFDF5',
    borderWidth: 1,
    borderColor: '#A7F3D0',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  claimReadyText: { fontSize: 11, fontWeight: '800', color: '#065F46' },
  claimReadyPillMuted: {
    marginTop: 12,
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  claimReadyTextMuted: { fontSize: 11, fontWeight: '700', color: '#6B7280' },
  missionList: { marginTop: 12, gap: 10 },
  missionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#F9FAFB',
    borderRadius: 12,
    padding: 12,
  },
  missionIcon: {
    width: 34,
    height: 34,
    borderRadius: 10,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    alignItems: 'center',
    justifyContent: 'center',
  },
  missionTitle: { fontSize: 13, fontWeight: '800', color: '#111827' },
  missionSub: { marginTop: 2, fontSize: 11, color: '#6B7280' },
  missionClaimBtn: {
    backgroundColor: '#111827',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 9,
    minWidth: 68,
    alignItems: 'center',
  },
  missionClaimText: { color: '#fff', fontSize: 12, fontWeight: '800' },
  missionState: { fontSize: 11, fontWeight: '800', color: '#6B7280' },
  rewardsErrorText: { marginTop: 8, fontSize: 12, color: '#B91C1C', lineHeight: 16 },
  streakProgramBlock: { marginTop: 12 },
  streakProgramLabel: { fontSize: 11, fontWeight: '800', color: '#6B7280', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 8 },
  streakChipsRow: { gap: 8, paddingRight: 4 },
  streakChip: {
    backgroundColor: '#F0FDF4',
    borderWidth: 1,
    borderColor: '#BBF7D0',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 8,
    minWidth: 100,
  },
  streakChipTitle: { fontSize: 11, fontWeight: '800', color: '#166534' },
  streakChipValue: { fontSize: 12, fontWeight: '700', color: '#111827', marginTop: 2 },
  recentRewardsBlock: { marginTop: 12 },
  recentRewardsLabel: { fontSize: 11, fontWeight: '800', color: '#6B7280', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 6 },
  recentRewardRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 6 },
  recentRewardText: { fontSize: 12, color: '#374151', fontWeight: '600', flex: 1 },
  missionEmpty: {
    backgroundColor: '#F9FAFB',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 12,
  },
  missionEmptyText: { fontSize: 12, color: '#6B7280', lineHeight: 18 },

  matchHint: { fontSize: 11, color: '#9CA3AF', marginTop: -4, marginBottom: 8 },
  matchLoading: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    paddingVertical: 28,
    alignItems: 'center',
  },
  matchCard: {
    flexDirection: 'row',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 10,
  },
  matchImageBox: {
    width: 96,
    height: 96,
    borderRadius: 10,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  matchBody: { flex: 1, justifyContent: 'space-between' },
  matchCategory: {
    fontSize: 10,
    fontWeight: '700',
    color: '#9CA3AF',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  matchTitle: { fontSize: 14, fontWeight: '700', color: '#111827', lineHeight: 18, marginTop: 2 },
  matchMetaRow: { flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 3 },
  matchMetaText: { fontSize: 11, color: '#6B7280', flex: 1 },
  matchPriceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 6,
  },
  matchPrice: { fontSize: 15, fontWeight: '800', color: '#111827' },
  matchCashbackPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: '#000',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  matchCashbackText: { color: '#fff', fontSize: 10, fontWeight: '700' },
  matchEmpty: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
  },
  matchEmptyTitle: { fontSize: 13, fontWeight: '700', color: '#111827' },
  matchEmptySub: { fontSize: 11, color: '#6B7280', marginTop: 2 },
  fraudBanner: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    backgroundColor: '#FFFBEB',
    borderWidth: 1,
    borderColor: '#FCD34D',
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  fraudBannerText: { flex: 1, fontSize: 13, color: '#78350F', lineHeight: 18 },
});
