import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  Image,
  ScrollView,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import api from '../services/client';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';

const CURRENCY = 'ZMW';
const fmt = (n) => `${CURRENCY} ${Number(n || 0).toFixed(2)}`;

/**
 * Sponsor-side screen for a "Buy for Me" request.
 *
 * Reached from:
 *   - scanning a request QR in ScanQrPay (auto-routes to this screen), or
 *   - opening a share link (future deep-link support).
 *
 * The screen is deliberately single-purpose: render the preview, then
 * show a single primary CTA (Pay & Send). All integrity rules live on
 * the server; we just visualise `can_pay` and let the backend reject
 * anything stale.
 */
export default function BuyForMe({ route, navigation }) {
  const { wallet, refreshWallet } = useAuth();
  const { confirm, alert } = useDialog();
  const insets = useSafeAreaInsets();

  const token = route?.params?.token;
  const [loading, setLoading] = useState(true);
  const [paying,  setPaying]  = useState(false);
  const [req, setReq] = useState(null);
  const [error, setError] = useState(null);

  const fetchRequest = useCallback(async () => {
    if (!token) {
      setError('No request token provided.');
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const res = await api.get(`/buy-requests/${token}`);
      setReq(res.data);
      setError(null);
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load this request.');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => { fetchRequest(); }, [fetchRequest]);

  const product  = req?.product;
  const price    = Number(product?.price || 0);
  const balance  = Number(wallet?.balance || 0);
  const canAfford = balance >= price && price > 0;

  const statusMeta = (() => {
    switch (req?.status) {
      case 'fulfilled':
        return { tone: 'success', label: 'Already paid', icon: 'check-circle' };
      case 'cancelled':
        return { tone: 'muted',   label: 'Cancelled',    icon: 'x-circle' };
      case 'expired':
        return { tone: 'muted',   label: 'Expired',      icon: 'clock' };
      default:
        return { tone: 'active',  label: 'Awaiting payment', icon: 'help-circle' };
    }
  })();

  const handlePay = async () => {
    if (!req?.can_pay) return;

    if (!canAfford) {
      const topUp = await confirm({
        title: 'Not enough balance',
        message: `You need ${fmt(price - balance)} more to help ${req.requester?.name || 'them'} buy this.`,
        tone: 'warn',
        confirmLabel: 'Top Up',
        cancelLabel: 'Cancel',
      });
      if (topUp) navigation.navigate('Fund');
      return;
    }

    const ok = await confirm({
      title: `Pay for ${req.requester?.name || 'them'}?`,
      message: 'The item will be marked as bought on their behalf and your wallet will be debited.',
      tone: 'default',
      icon: 'gift',
      confirmLabel: `Pay ${fmt(price)}`,
      cancelLabel: 'Cancel',
      details: [
        { label: 'Product',   value: product.title },
        { label: 'Price',     value: fmt(price) },
        { label: 'Recipient', value: req.requester?.name || '—' },
      ],
    });
    if (!ok) return;

    setPaying(true);
    try {
      const res = await api.post(`/buy-requests/${token}/fulfill`);
      await refreshWallet();
      await alert({
        title: 'Thanks for helping out',
        message: `You just bought "${product.title}" for ${req.requester?.name || 'them'}.`,
        tone: 'success',
        details: [
          { label: 'Paid', value: fmt(res.data?.sale?.gross_amount) },
          { label: 'Your cashback', value: `+ ${fmt(res.data?.sale?.cashback_amount)}`, tone: 'success' },
        ],
        confirmLabel: 'Done',
      });
      navigation.goBack();
    } catch (err) {
      await alert({
        title: 'Payment failed',
        message: err?.response?.data?.message || 'Could not complete this payment.',
        tone: 'danger',
      });
      // Re-fetch to get the latest status (e.g. expired/cancelled).
      fetchRequest();
    } finally {
      setPaying(false);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <Header onBack={() => navigation.goBack()} />
        <View style={styles.loadingBox}>
          <ActivityIndicator size="large" color="#111827" />
          <Text style={styles.loadingText}>Loading request…</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error || !req || !product) {
    return (
      <SafeAreaView style={styles.container}>
        <Header onBack={() => navigation.goBack()} />
        <View style={styles.loadingBox}>
          <View style={styles.errorIcon}>
            <Feather name="alert-triangle" size={22} color="#DC2626" />
          </View>
          <Text style={styles.errorTitle}>Request unavailable</Text>
          <Text style={styles.errorText}>{error || 'This request could not be found.'}</Text>
          <TouchableOpacity style={styles.errorBtn} onPress={() => navigation.goBack()}>
            <Text style={styles.errorBtnText}>Go back</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const bottomPad = Math.max(insets.bottom, 16);
  const payDisabled = !req.can_pay || paying;

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <Header onBack={() => navigation.goBack()} />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 14 }}>
        {/* Requester callout */}
        <View style={styles.requesterBox}>
          <View style={styles.requesterAvatar}>
            <Text style={styles.requesterInitials}>
              {(req.requester?.name || 'U').slice(0, 2).toUpperCase()}
            </Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.requesterIntro}>
              <Text style={styles.requesterName}>{req.requester?.name || 'A user'}</Text> is asking you
              to cover this purchase.
            </Text>
            {req.note ? (
              <View style={styles.noteBubble}>
                <Text style={styles.noteText}>"{req.note}"</Text>
              </View>
            ) : null}
          </View>
        </View>

        {/* Product card */}
        <View style={styles.productCard}>
          <View style={styles.productImageBox}>
            {product.image_url ? (
              <Image source={{ uri: product.image_url }} style={styles.productImage} resizeMode="cover" />
            ) : (
              <Feather name="package" size={32} color="#9CA3AF" />
            )}
          </View>
          <View style={{ padding: 14, gap: 4 }}>
            {product.category ? (
              <Text style={styles.productCategory}>{product.category}</Text>
            ) : null}
            <Text style={styles.productTitle}>{product.title}</Text>
            <Text style={styles.productPrice}>{fmt(price)}</Text>
            {product.seller ? (
              <Text style={styles.productSeller} numberOfLines={1}>Sold by {product.seller.name}</Text>
            ) : null}
          </View>
        </View>

        {/* Status pill */}
        <View style={[styles.statusPill, statusStyle(statusMeta.tone)]}>
          <Feather name={statusMeta.icon} size={14} color={statusTextColor(statusMeta.tone)} />
          <Text style={[styles.statusPillText, { color: statusTextColor(statusMeta.tone) }]}>
            {statusMeta.label}
          </Text>
        </View>

        {/* Wallet summary (sponsor-specific) */}
        <View style={styles.walletCard}>
          <View style={styles.walletRow}>
            <Text style={styles.walletLabel}>Your balance</Text>
            <Text style={styles.walletValue}>{fmt(balance)}</Text>
          </View>
          <View style={styles.walletRow}>
            <Text style={styles.walletLabel}>Amount to pay</Text>
            <Text style={styles.walletValue}>- {fmt(price)}</Text>
          </View>
          <View style={styles.walletDivider} />
          <View style={styles.walletRow}>
            <Text style={styles.walletLabelBold}>Balance after</Text>
            <Text style={[
              styles.walletValueBold,
              canAfford ? {} : { color: '#DC2626' },
            ]}>
              {fmt(Math.max(0, balance - price))}
            </Text>
          </View>
          <View style={styles.walletNote}>
            <Feather name="gift" size={13} color="#047857" />
            <Text style={styles.walletNoteText}>
              You'll still earn {fmt(price * 0.02)} cashback for this purchase.
            </Text>
          </View>
        </View>
      </ScrollView>

      {/* Sticky footer */}
      <View style={[styles.footer, { paddingBottom: bottomPad }]}>
        <TouchableOpacity
          style={[styles.payBtn, payDisabled && styles.payBtnDisabled]}
          onPress={handlePay}
          disabled={payDisabled}
          activeOpacity={0.85}
        >
          {paying ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <>
              <Feather
                name={req.can_pay ? 'send' : 'slash'}
                size={16}
                color="#fff"
              />
              <Text style={styles.payBtnText}>
                {req.can_pay
                  ? `Pay ${fmt(price)} for ${req.requester?.name?.split(' ')[0] || 'them'}`
                  : req.status === 'fulfilled'
                    ? 'Already paid'
                    : req.status === 'cancelled'
                      ? 'Request cancelled'
                      : req.status === 'expired'
                        ? 'Request expired'
                        : 'Cannot pay'}
              </Text>
            </>
          )}
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

function Header({ onBack }) {
  return (
    <View style={styles.header}>
      <TouchableOpacity onPress={onBack} style={styles.backBtn}>
        <Feather name="arrow-left" size={20} color="#111827" />
      </TouchableOpacity>
      <Text style={styles.headerTitle}>Buy for a friend</Text>
      <View style={{ width: 36 }} />
    </View>
  );
}

/* ── Status colour helpers ──────────────────────────────────── */
function statusStyle(tone) {
  switch (tone) {
    case 'success': return { backgroundColor: '#DCFCE7', borderColor: '#86EFAC' };
    case 'muted':   return { backgroundColor: '#F3F4F6', borderColor: '#E5E7EB' };
    default:        return { backgroundColor: '#EFF6FF', borderColor: '#BFDBFE' };
  }
}
function statusTextColor(tone) {
  switch (tone) {
    case 'success': return '#047857';
    case 'muted':   return '#6B7280';
    default:        return '#1D4ED8';
  }
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F9FAFB' },

  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  backBtn: {
    width: 36, height: 36, borderRadius: 8,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
  },
  headerTitle: { fontSize: 17, fontWeight: '800' },

  loadingBox: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 10,
    padding: 24,
  },
  loadingText: { color: '#6B7280', fontSize: 13 },

  errorIcon: {
    width: 52, height: 52, borderRadius: 26,
    backgroundColor: '#FEE2E2',
    justifyContent: 'center', alignItems: 'center',
  },
  errorTitle: { fontSize: 16, fontWeight: '800', color: '#111827' },
  errorText:  { fontSize: 13, color: '#6B7280', textAlign: 'center' },
  errorBtn: {
    marginTop: 8,
    backgroundColor: '#111827',
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 10,
  },
  errorBtnText: { color: '#fff', fontWeight: '800' },

  requesterBox: {
    flexDirection: 'row',
    gap: 12,
    backgroundColor: '#fff',
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  requesterAvatar: {
    width: 44, height: 44, borderRadius: 22,
    backgroundColor: '#111827',
    justifyContent: 'center', alignItems: 'center',
  },
  requesterInitials: { color: '#fff', fontWeight: '800' },
  requesterIntro: { fontSize: 14, color: '#374151', lineHeight: 20 },
  requesterName:  { fontWeight: '800', color: '#111827' },
  noteBubble: {
    marginTop: 8,
    padding: 10,
    borderRadius: 10,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  noteText: { fontSize: 12, fontStyle: 'italic', color: '#6B7280' },

  productCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    overflow: 'hidden',
  },
  productImageBox: {
    height: 180,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  productImage: { width: '100%', height: '100%' },
  productCategory: {
    fontSize: 10, fontWeight: '800',
    color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.5,
  },
  productTitle:  { fontSize: 16, fontWeight: '800', color: '#111827' },
  productPrice:  { fontSize: 18, fontWeight: '900', color: '#111827', marginTop: 2 },
  productSeller: { fontSize: 12, color: '#6B7280', marginTop: 2 },

  statusPill: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
  },
  statusPillText: { fontSize: 12, fontWeight: '800' },

  walletCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
    gap: 8,
  },
  walletRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  walletLabel:      { fontSize: 13, color: '#6B7280' },
  walletLabelBold:  { fontSize: 13, color: '#111827', fontWeight: '800' },
  walletValue:      { fontSize: 13, color: '#111827', fontWeight: '700' },
  walletValueBold:  { fontSize: 15, color: '#111827', fontWeight: '900' },
  walletDivider: { height: 1, backgroundColor: '#E5E7EB', marginVertical: 4 },
  walletNote: {
    flexDirection: 'row',
    gap: 6,
    alignItems: 'center',
    marginTop: 4,
    padding: 10,
    backgroundColor: '#ECFDF5',
    borderRadius: 10,
  },
  walletNoteText: { fontSize: 11, color: '#047857', fontWeight: '700', flex: 1 },

  footer: {
    paddingHorizontal: 16,
    paddingTop: 12,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
  },
  payBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#111827',
    borderRadius: 12,
    paddingVertical: 14,
  },
  payBtnDisabled: { backgroundColor: '#9CA3AF' },
  payBtnText: { color: '#fff', fontWeight: '800', fontSize: 14 },
});
