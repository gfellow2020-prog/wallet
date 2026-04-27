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

const CURRENCY = 'ZMW';
const fmt = (n) => `${CURRENCY} ${Number(n || 0).toFixed(2)}`;

export default function PurchasedProducts({ navigation }) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);

  const fetchMine = useCallback(async ({ isRefresh = false } = {}) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);

    try {
      const res = await api.get('/buy-requests/mine');
      const all = res.data?.data || [];
      const fulfilled = all.filter((r) => r.status === 'fulfilled');
      setItems(fulfilled);
      setError(null);
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load purchased products.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      fetchMine();
    }, [fetchMine])
  );

  const openProduct = (row) => {
    const product = row?.product;
    if (!product?.id) return;
    navigation.navigate('ProductDetail', { productId: product.id, product });
  };

  const renderItem = ({ item }) => {
    const product = item.product || {};
    const paidBy = item.fulfilled_by?.name || 'Someone';
    const when = item.fulfilled_at ? relativeTime(item.fulfilled_at) : '';

    return (
      <TouchableOpacity style={styles.row} onPress={() => openProduct(item)} activeOpacity={0.85}>
        <View style={styles.thumb}>
          {product.image_url ? (
            <Image source={{ uri: product.image_url }} style={styles.thumbImg} />
          ) : (
            <Feather name="package" size={18} color="#9CA3AF" />
          )}
        </View>

        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.title} numberOfLines={2}>{product.title || 'Product'}</Text>
          <Text style={styles.meta} numberOfLines={1}>
            Purchased by {paidBy}{when ? ` · ${when}` : ''}
          </Text>
          <View style={styles.pricePill}>
            <Text style={styles.priceText}>{fmt(product.price)}</Text>
          </View>
        </View>

        <Feather name="chevron-right" size={18} color="#D1D5DB" />
      </TouchableOpacity>
    );
  };

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="arrow-left" size={20} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Purchased products</Text>
        <View style={{ width: 36 }} />
      </View>

      {loading ? (
        <View style={styles.stateBox}>
          <ActivityIndicator size="large" color="#111827" />
          <Text style={styles.stateText}>Loading your purchases…</Text>
        </View>
      ) : error ? (
        <View style={styles.stateBox}>
          <View style={[styles.stateIcon, { backgroundColor: '#FEE2E2' }]}>
            <Feather name="alert-triangle" size={20} color="#DC2626" />
          </View>
          <Text style={styles.stateTitle}>Something went wrong</Text>
          <Text style={styles.stateText}>{error}</Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => fetchMine()}>
            <Text style={styles.retryBtnText}>Try again</Text>
          </TouchableOpacity>
        </View>
      ) : items.length === 0 ? (
        <View style={styles.stateBox}>
          <View style={[styles.stateIcon, { backgroundColor: '#F3F4F6' }]}>
            <Feather name="shopping-bag" size={22} color="#6B7280" />
          </View>
          <Text style={styles.stateTitle}>No purchases yet</Text>
          <Text style={styles.stateText}>
            When someone buys you an item via Buy-for-Me, it will appear here.
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
              onRefresh={() => fetchMine({ isRefresh: true })}
              tintColor="#111827"
            />
          }
        />
      )}
    </SafeAreaView>
  );
}

function relativeTime(iso) {
  if (!iso) return '';
  const then = new Date(iso).getTime();
  const secs = Math.max(0, Math.floor((Date.now() - then) / 1000));
  if (secs < 60) return 'just now';
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
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
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
    width: 52,
    height: 52,
    borderRadius: 26,
    justifyContent: 'center',
    alignItems: 'center',
  },
  stateTitle: { fontSize: 16, fontWeight: '800', color: '#111827' },
  stateText: { fontSize: 13, color: '#6B7280', textAlign: 'center' },
  retryBtn: {
    marginTop: 8,
    backgroundColor: '#111827',
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 10,
  },
  retryBtnText: { color: '#fff', fontWeight: '800' },

  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 12,
  },
  thumb: {
    width: 56,
    height: 56,
    borderRadius: 12,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  thumbImg: { width: '100%', height: '100%' },
  title: { fontSize: 14, fontWeight: '800', color: '#111827' },
  meta: { fontSize: 11, color: '#6B7280', marginTop: 4 },
  pricePill: {
    marginTop: 8,
    alignSelf: 'flex-start',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
    backgroundColor: '#EFF6FF',
    borderWidth: 1,
    borderColor: '#BFDBFE',
  },
  priceText: { fontSize: 12, fontWeight: '800', color: '#1D4ED8' },
});

