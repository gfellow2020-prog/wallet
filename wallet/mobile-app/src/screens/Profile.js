import React, { useCallback, useState } from 'react';
import { SafeAreaView, ScrollView, View, Text, TouchableOpacity, StyleSheet, ActivityIndicator, Image, Share, Clipboard } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import * as ImagePicker from 'expo-image-picker';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import api from '../services/client';
import { fetchRewardsSummary } from '../services/rewards';
import { compressImageAsset } from '../utils/imageCompression';
import { optImageUrl } from '../utils/optImage';

export default function Profile({ navigation }) {
  const { user, wallet, logout, refreshWallet } = useAuth();
  const { confirm, alert } = useDialog();
  const [loading, setLoading] = useState(false);
  const [uploadingPhoto, setUploadingPhoto] = useState(false);
  const [inboxCount, setInboxCount] = useState(0);
  const [rewardsSummary, setRewardsSummary] = useState(null);

  const initials = user?.name?.split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase() || 'U';

  // Refresh pending Buy-for-Me count each time Profile gains focus so the
  // badge stays accurate after the user fulfils requests and comes back.
  useFocusEffect(
    useCallback(() => {
      let cancelled = false;
      (async () => {
        try {
          const [inboxRes, rewardsRes] = await Promise.all([
            api.get('/buy-requests/incoming/count'),
            fetchRewardsSummary(),
          ]);
          if (!cancelled) {
            setInboxCount(Number(inboxRes.data?.count || 0));
            setRewardsSummary(rewardsRes);
          }
        } catch {
          // silent — the Inbox screen itself handles errors loudly
        }
      })();
      return () => { cancelled = true; };
    }, [])
  );

  const copyExtracash = async () => {
    if (!user?.extracash_number) return;
    try {
      Clipboard.setString(user.extracash_number);
      await alert({
        title: 'ExtraCash number copied',
        message: 'Paste it in a chat — friends can look you up instantly.',
        tone: 'success',
      });
    } catch {}
  };

  const streakCount = Number(rewardsSummary?.streak?.current_count || 0);
  const claimableCount = Number(rewardsSummary?.missions?.claimable_missions || 0);

  const shareExtracash = async () => {
    if (!user?.extracash_number) return;
    try {
      await Share.share({
        message:
          `My ExtraCash number is ${user.extracash_number}. ` +
          `Enter it in ExtraCash under "Buy for Me" to send me a request.`,
      });
    } catch {}
  };

  const handleLogout = async () => {
    const ok = await confirm({
      title: 'Sign out?',
      message: 'Are you sure you want to sign out of your account?',
      tone: 'warn',
      icon: 'log-out',
      confirmLabel: 'Sign out',
      cancelLabel: 'Cancel',
      confirmTone: 'danger',
    });
    if (!ok) return;
    setLoading(true);
    await logout();
    setLoading(false);
  };

  const infoRow = (icon, label, value) => (
    <View style={styles.infoRow} key={label}>
      <View style={styles.infoIcon}><Feather name={icon} size={16} color="#374151" /></View>
      <View style={{ flex: 1 }}>
        <Text style={styles.infoLabel}>{label}</Text>
        <Text style={styles.infoValue}>{value || '—'}</Text>
      </View>
    </View>
  );

  const updateProfilePhoto = async () => {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      return alert({
        title: 'Permission required',
        message: 'Camera permission is needed to update your profile photo.',
        tone: 'warn',
      });
    }

    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      quality: 0.8,
    });

    if (result.canceled) return;
    const asset = result.assets?.[0];
    if (!asset?.uri) return;

    setUploadingPhoto(true);
    try {
      let out = asset;
      try {
        out = await compressImageAsset(asset);
      } catch {
        // fallback to original
      }
      const form = new FormData();
      form.append('profile_photo', {
        uri: out.uri,
        name: `profile-${Date.now()}.jpg`,
        type: out.mimeType || asset.mimeType || 'image/jpeg',
      });

      await api.post('/me/profile-photo', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await refreshWallet();
      await alert({
        title: 'Photo updated',
        message: 'Your profile photo has been updated.',
        tone: 'success',
      });
    } catch (err) {
      const msg = err?.response?.data?.message || 'Unable to update profile photo right now.';
      await alert({ title: 'Upload failed', message: msg, tone: 'danger' });
    } finally {
      setUploadingPhoto(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <View style={{ width: 36 }} />
        <Text style={styles.title}>Profile</Text>
        <View style={{ width: 36 }} />
      </View>

      <ScrollView contentContainerStyle={{ paddingBottom: 32 }}>
        {/* Avatar + name */}
        <View style={styles.heroSection}>
          {user?.profile_photo_url ? (
            <Image source={{ uri: optImageUrl(user.profile_photo_url, { w: 320, q: 65 }) }} style={styles.photoLarge} />
          ) : (
            <View style={styles.avatarLarge}>
              <Text style={styles.avatarText}>{initials}</Text>
            </View>
          )}
          <Text style={styles.userName}>{user?.name}</Text>
          <Text style={styles.userEmail}>{user?.email}</Text>
          <TouchableOpacity style={styles.photoActionBtn} onPress={updateProfilePhoto} disabled={uploadingPhoto}>
            {uploadingPhoto ? <ActivityIndicator size="small" color="#111827" /> : <Text style={styles.photoActionText}>Update Photo (Camera)</Text>}
          </TouchableOpacity>
        </View>

        {/* ── ExtraCash Number card ─────────────────────────────
            Front-and-centre because it's the primary way other users
            will send Buy-for-Me requests to this user. Copy + Share
            give parity with the web flow. */}
        {user?.extracash_number ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>ExtraCash Number</Text>
            <View style={styles.ecCard}>
              <View style={styles.ecTop}>
                <View style={styles.ecIconWrap}>
                  <Feather name="hash" size={18} color="#047857" />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.ecLabel}>Your public handle</Text>
                  <Text style={styles.ecNumber} selectable>{user.extracash_number}</Text>
                </View>
              </View>
              <Text style={styles.ecHint}>
                Share this number so friends can send you Buy-for-Me requests directly.
              </Text>
              <View style={styles.ecActions}>
                <TouchableOpacity style={styles.ecActionBtn} onPress={copyExtracash} activeOpacity={0.85}>
                  <Feather name="copy" size={14} color="#111827" />
                  <Text style={styles.ecActionText}>Copy</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.ecActionBtn, styles.ecActionPrimary]} onPress={shareExtracash} activeOpacity={0.85}>
                  <Feather name="share-2" size={14} color="#fff" />
                  <Text style={[styles.ecActionText, { color: '#fff' }]}>Share</Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        ) : null}

        {/* ── Buy-for-Me Inbox entry with live badge ─────────── */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Requests</Text>
          <TouchableOpacity
            style={styles.rowLink}
            onPress={() => navigation.navigate('BuyRequestsInbox')}
            activeOpacity={0.85}
          >
            <View style={[styles.infoIcon, { backgroundColor: '#EFF6FF' }]}>
              <Feather name="inbox" size={16} color="#1D4ED8" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.rowLinkTitle}>Buy-for-Me Inbox</Text>
              <Text style={styles.rowLinkSub}>
                {inboxCount > 0
                  ? `${inboxCount} pending ${inboxCount === 1 ? 'request' : 'requests'}`
                  : 'Requests from friends show up here'}
              </Text>
            </View>
            {inboxCount > 0 ? (
              <View style={styles.badge}>
                <Text style={styles.badgeText}>{inboxCount > 99 ? '99+' : inboxCount}</Text>
              </View>
            ) : null}
            <Feather name="chevron-right" size={18} color="#9CA3AF" />
          </TouchableOpacity>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Rewards</Text>
          <TouchableOpacity
            style={styles.rowLink}
            onPress={() => navigation.navigate('RewardsHub')}
            activeOpacity={0.85}
          >
            <View style={[styles.infoIcon, { backgroundColor: '#ECFDF5' }]}>
              <Feather name="award" size={16} color="#047857" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.rowLinkTitle}>Streaks &amp; Missions</Text>
              <Text style={styles.rowLinkSub}>
                {`${streakCount}-day streak`}
                {claimableCount > 0 ? ` · ${claimableCount} reward ready` : ' · Keep your daily loop alive'}
              </Text>
            </View>
            {claimableCount > 0 ? (
              <View style={[styles.badge, { backgroundColor: '#047857' }]}>
                <Text style={styles.badgeText}>{claimableCount > 99 ? '99+' : claimableCount}</Text>
              </View>
            ) : null}
            <Feather name="chevron-right" size={18} color="#9CA3AF" />
          </TouchableOpacity>
        </View>

        {/* Wallet info */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Wallet</Text>
          <View style={styles.card}>
            {infoRow('dollar-sign', 'Balance', `ZMW ${parseFloat(wallet?.balance || 0).toFixed(2)}`)}
            {infoRow('credit-card', 'Card Number', wallet?.card_number)}
            {infoRow('calendar', 'Expiry', wallet?.expiry)}
            {infoRow('tag', 'Currency', wallet?.currency || 'ZMW')}
          </View>
        </View>

        {/* Account */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Account</Text>
          <View style={styles.card}>
            {infoRow('user', 'Full Name', user?.name)}
            {infoRow('mail', 'Email', user?.email)}
            {infoRow('shield', 'NRC Number', user?.nrc_number)}
            {infoRow('clock', 'Member Since', user?.created_at ? new Date(user.created_at).toLocaleDateString() : null)}
          </View>
        </View>

        <View style={[styles.section, { marginTop: 16 }]}>
          <TouchableOpacity
            style={styles.kycBtn}
            onPress={() => navigation.navigate('Kyc')}
          >
            <Feather name="shield" size={18} color="#000" />
            <Text style={styles.kycBtnText}>Identity Verification (KYC)</Text>
            <Feather name="chevron-right" size={18} color="#9CA3AF" style={{ marginLeft: 'auto' }} />
          </TouchableOpacity>
        </View>

        <View style={[styles.section, { marginTop: 16 }]}>
          <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout} disabled={loading}>
            {loading ? <ActivityIndicator color="#fff" /> : (
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                <Feather name="log-out" size={16} color="#fff" />
                <Text style={styles.logoutText}>Sign Out</Text>
              </View>
            )}
          </TouchableOpacity>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F3F4F6' },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#fff', paddingHorizontal: 16, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  backBtn: { width: 36, height: 36, borderRadius: 8, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 17, fontWeight: '800' },
  heroSection: { backgroundColor: '#fff', alignItems: 'center', paddingVertical: 28, borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  avatarLarge: { width: 72, height: 72, borderRadius: 36, backgroundColor: '#000', justifyContent: 'center', alignItems: 'center', marginBottom: 12 },
  photoLarge: { width: 80, height: 80, borderRadius: 40, marginBottom: 12, backgroundColor: '#E5E7EB' },
  avatarText: { color: '#fff', fontSize: 26, fontWeight: '800' },
  userName: { fontSize: 18, fontWeight: '800', color: '#111827' },
  userEmail: { fontSize: 13, color: '#6B7280', marginTop: 4 },
  photoActionBtn: { marginTop: 12, paddingHorizontal: 12, paddingVertical: 8, borderRadius: 8, borderWidth: 1, borderColor: '#D1D5DB', backgroundColor: '#F9FAFB' },
  photoActionText: { fontSize: 12, fontWeight: '700', color: '#111827' },
  section: { padding: 16, paddingBottom: 0 },
  sectionTitle: { fontSize: 12, fontWeight: '700', color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 8 },
  card: { backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB', overflow: 'hidden' },
  infoRow: { flexDirection: 'row', alignItems: 'center', padding: 14, gap: 12, borderBottomWidth: 1, borderBottomColor: '#F3F4F6' },
  infoIcon: { width: 32, height: 32, borderRadius: 8, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  infoLabel: { fontSize: 11, color: '#9CA3AF', fontWeight: '500' },
  infoValue: { fontSize: 14, color: '#111827', fontWeight: '600', marginTop: 1 },
  logoutBtn: { backgroundColor: '#000', paddingVertical: 14, borderRadius: 8, alignItems: 'center' },
  logoutText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  kycBtn: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    backgroundColor: '#fff', borderRadius: 12, padding: 16,
    borderWidth: 1, borderColor: '#E5E7EB',
  },
  kycBtnText: { fontSize: 15, fontWeight: '600', color: '#111' },

  /* ── ExtraCash Number card ────────────────────────────────── */
  ecCard: {
    backgroundColor: '#ECFDF5',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#A7F3D0',
    padding: 14,
    gap: 10,
  },
  ecTop: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  ecIconWrap: {
    width: 36, height: 36, borderRadius: 10,
    backgroundColor: '#fff',
    justifyContent: 'center', alignItems: 'center',
  },
  ecLabel:  { fontSize: 11, fontWeight: '700', color: '#047857', letterSpacing: 0.3 },
  ecNumber: { fontSize: 22, fontWeight: '900', color: '#065F46', letterSpacing: 2, marginTop: 2 },
  ecHint:   { fontSize: 12, color: '#047857', lineHeight: 17 },
  ecActions: { flexDirection: 'row', gap: 8 },
  ecActionBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#A7F3D0',
    paddingVertical: 10,
    borderRadius: 10,
  },
  ecActionPrimary: { backgroundColor: '#047857', borderColor: '#047857' },
  ecActionText: { fontSize: 12, fontWeight: '800', color: '#111827' },

  /* ── Generic link row (used by Buy-for-Me Inbox) ─────────── */
  rowLink: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
  },
  rowLinkTitle: { fontSize: 14, fontWeight: '800', color: '#111827' },
  rowLinkSub:   { fontSize: 11, color: '#6B7280', marginTop: 2 },
  badge: {
    minWidth: 22, height: 22, paddingHorizontal: 7,
    borderRadius: 11,
    backgroundColor: '#DC2626',
    justifyContent: 'center', alignItems: 'center',
  },
  badgeText: { color: '#fff', fontSize: 11, fontWeight: '800' },
});
