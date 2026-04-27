import React, { useCallback, useState } from 'react';
import {
  View,
  Text,
  Image,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useFocusEffect } from '@react-navigation/native';
import { Feather } from '@expo/vector-icons';
import api from '../services/client';
import { ensureDirectConversation } from '../services/messaging';

const CURRENCY = 'ZMW';
const fmt = (n) => `${CURRENCY} ${Number(n || 0).toFixed(2)}`;

/**
 * Inbox of *incoming* Buy-for-Me requests — i.e. requests where another
 * user targeted me via my ExtraCash Number.
 *
 * Tap a row → navigate to the existing BuyForMe sponsor screen to review
 * and (optionally) pay on the requester's behalf. Pull-to-refresh on the
 * list and re-fetches automatically when the screen regains focus.
 */
export default function BuyRequestsInbox({ navigation }) {
  const [items, setItems]       = useState([]);
  const [loading, setLoading]   = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError]       = useState(null);

  const fetchInbox = useCallback(async ({ isRefresh = false } = {}) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    try {
      const res = await api.get('/buy-requests/incoming');
      setItems(res.data?.data || []);
      setError(null);
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load your inbox.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  // Re-fetch every time we return to this screen (e.g. after paying).
  useFocusEffect(
    useCallback(() => { fetchInbox(); }, [fetchInbox])
  );

  const openRequest = (item) => {
    navigation.navigate('BuyForMe', { token: item.token });
  };

  const openChatWithRequester = async (item) => {
    const requester = item.requester || {};
    if (!requester.id) return;
    try {
      const conversationId = await ensureDirectConversation(requester.id);
      if (conversationId) {
        navigation.navigate('MessageThread', {
          conversationId,
          otherUser: { id: requester.id, name: requester.name, email: requester.email },
          context: {
            type: 'buy_request',
            title: `Buy-for-Me request · ${item.product?.title || 'Item'}`,
          },
        });
      }
    } catch {
      // ignore
    }
  };

  const renderItem = ({ item }) => {
    const product = item.product || {};
    const requester = item.requester || {};
    const initials = (requester.name || 'U')
      .split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();

    const age = relativeTime(item.created_at);

    return (
      <TouchableOpacity
        style={styles.row}
        onPress={() => openRequest(item)}
        activeOpacity={0.85}
      >
        <View style={styles.rowTop}>
          <View style={styles.avatar}>
            {requester.profile_photo_url ? (
              <Image source={{ uri: requester.profile_photo_url }} style={styles.avatarImg} />
            ) : (
              <Text style={styles.avatarInitials}>{initials}</Text>
            )}
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.requesterName} numberOfLines={1}>
              {requester.name || 'Someone'}
            </Text>
            <Text style={styles.requesterSub}>
              is asking you to buy this · {age}
            </Text>
          </View>
          <TouchableOpacity
            onPress={() => openChatWithRequester(item)}
            style={styles.chatBtn}
            activeOpacity={0.85}
          >
            <Feather name="message-circle" size={16} color="#111827" />
          </TouchableOpacity>
          <View style={styles.amountPill}>
            <Text style={styles.amountText}>{fmt(product.price)}</Text>
          </View>
        </View>

        <View style={styles.productStripe}>
          <View style={styles.productThumb}>
            {product.image_url ? (
              <Image source={{ uri: product.image_url }} style={styles.productThumbImg} />
            ) : (
              <Feather name="package" size={18} color="#9CA3AF" />
            )}
          </View>
          <View style={{ flex: 1, gap: 2 }}>
            <Text style={styles.productTitle} numberOfLines={2}>{product.title || 'Product'}</Text>
            {product.seller?.name ? (
              <Text style={styles.productSeller} numberOfLines={1}>Sold by {product.seller.name}</Text>
            ) : null}
          </View>
          <Feather name="chevron-right" size={18} color="#9CA3AF" />
        </View>

        {item.note ? (
          <View style={styles.noteBubble}>
            <Feather name="message-circle" size={12} color="#6B7280" />
            <Text style={styles.noteText} numberOfLines={2}>"{item.note}"</Text>
          </View>
        ) : null}
      </TouchableOpacity>
    );
  };

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="arrow-left" size={20} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Buy-for-Me Inbox</Text>
        <View style={{ width: 36 }} />
      </View>

      {loading ? (
        <View style={styles.stateBox}>
          <ActivityIndicator size="large" color="#111827" />
          <Text style={styles.stateText}>Loading your inbox…</Text>
        </View>
      ) : error ? (
        <View style={styles.stateBox}>
          <View style={[styles.stateIcon, { backgroundColor: '#FEE2E2' }]}>
            <Feather name="alert-triangle" size={20} color="#DC2626" />
          </View>
          <Text style={styles.stateTitle}>Something went wrong</Text>
          <Text style={styles.stateText}>{error}</Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => fetchInbox()}>
            <Text style={styles.retryBtnText}>Try again</Text>
          </TouchableOpacity>
        </View>
      ) : items.length === 0 ? (
        <View style={styles.stateBox}>
          <View style={[styles.stateIcon, { backgroundColor: '#F3F4F6' }]}>
            <Feather name="inbox" size={22} color="#6B7280" />
          </View>
          <Text style={styles.stateTitle}>No pending requests</Text>
          <Text style={styles.stateText}>
            Friends who ask you to buy them something will show up here.
          </Text>
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(i) => i.token}
          renderItem={renderItem}
          contentContainerStyle={{ padding: 16, gap: 12 }}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => fetchInbox({ isRefresh: true })}
              tintColor="#111827"
            />
          }
        />
      )}
    </SafeAreaView>
  );
}

/* Quick relative-time helper — avoids pulling in a date library for one use. */
function relativeTime(iso) {
  if (!iso) return '';
  const then = new Date(iso).getTime();
  const secs = Math.max(0, Math.floor((Date.now() - then) / 1000));
  if (secs < 60)   return 'just now';
  if (secs < 3600) return `${Math.floor(secs / 60)}m ago`;
  if (secs < 86400) return `${Math.floor(secs / 3600)}h ago`;
  return `${Math.floor(secs / 86400)}d ago`;
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F9FAFB' },

  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 14,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  backBtn: {
    width: 36, height: 36, borderRadius: 8,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
  },
  headerTitle: { fontSize: 17, fontWeight: '800', color: '#111827' },

  stateBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
    gap: 10,
  },
  stateIcon: {
    width: 52, height: 52, borderRadius: 26,
    justifyContent: 'center', alignItems: 'center',
  },
  stateTitle: { fontSize: 16, fontWeight: '800', color: '#111827' },
  stateText:  { fontSize: 13, color: '#6B7280', textAlign: 'center' },
  retryBtn: {
    marginTop: 8,
    backgroundColor: '#111827',
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 10,
  },
  retryBtnText: { color: '#fff', fontWeight: '800' },

  row: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
    gap: 12,
  },
  rowTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  avatar: {
    width: 40, height: 40, borderRadius: 20,
    backgroundColor: '#111827',
    justifyContent: 'center', alignItems: 'center',
    overflow: 'hidden',
  },
  avatarImg: { width: '100%', height: '100%' },
  avatarInitials: { color: '#fff', fontWeight: '800', fontSize: 13 },
  requesterName: { fontSize: 14, fontWeight: '800', color: '#111827' },
  requesterSub:  { fontSize: 11, color: '#6B7280', marginTop: 1 },

  amountPill: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: '#EFF6FF',
    borderWidth: 1,
    borderColor: '#BFDBFE',
  },
  amountText: { fontSize: 12, fontWeight: '800', color: '#1D4ED8' },

  chatBtn: {
    width: 36, height: 36, borderRadius: 10,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
  },

  productStripe: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#F9FAFB',
    borderRadius: 10,
    padding: 10,
  },
  productThumb: {
    width: 48, height: 48, borderRadius: 8,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
    overflow: 'hidden',
  },
  productThumbImg: { width: '100%', height: '100%' },
  productTitle:  { fontSize: 13, fontWeight: '800', color: '#111827' },
  productSeller: { fontSize: 11, color: '#6B7280' },

  noteBubble: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 6,
    padding: 10,
    backgroundColor: '#FEF3C7',
    borderRadius: 10,
  },
  noteText: { flex: 1, fontSize: 12, color: '#92400E', fontStyle: 'italic', lineHeight: 17 },
});
