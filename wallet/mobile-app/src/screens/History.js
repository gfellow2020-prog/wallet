import React, { useEffect, useState, useCallback } from 'react';
import { SafeAreaView, View, Text, FlatList, StyleSheet, ActivityIndicator, RefreshControl, TouchableOpacity } from 'react-native';
import { Feather } from '@expo/vector-icons';
import api from '../services/client';

export default function History({ navigation }) {
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetch = async () => {
    try {
      const res = await api.get('/wallet/transactions');
      setTransactions(res.data?.transactions ?? res.data ?? []);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { fetch(); }, []);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetch();
    setRefreshing(false);
  }, []);

  const renderItem = ({ item }) => (
    <View style={styles.row}>
      <View style={[styles.icon, { backgroundColor: item.type === 'credit' ? '#F0FDF4' : '#FFF7ED' }]}>
        <Feather name={item.type === 'credit' ? 'arrow-down-left' : 'arrow-up-right'} size={16} color={item.type === 'credit' ? '#16A34A' : '#EA580C'} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={styles.narration} numberOfLines={1}>{item.narration}</Text>
        <Text style={styles.date}>{item.date}</Text>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Text style={[styles.amount, { color: item.type === 'credit' ? '#16A34A' : '#DC2626' }]}>
          {item.type === 'credit' ? '+' : '-'}{item.amount}
        </Text>
        <View style={[styles.badge, { backgroundColor: item.status === 'completed' ? '#F0FDF4' : '#FEF3C7' }]}>
          <Text style={{ fontSize: 10, fontWeight: '600', color: item.status === 'completed' ? '#16A34A' : '#D97706' }}>
            {item.status}
          </Text>
        </View>
      </View>
    </View>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="arrow-left" size={20} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.title}>Transaction History</Text>
        <View style={{ width: 36 }} />
      </View>

      {loading ? (
        <ActivityIndicator color="#000" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={transactions}
          keyExtractor={item => String(item.id)}
          renderItem={renderItem}
          contentContainerStyle={{ padding: 16, gap: 0 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Feather name="inbox" size={40} color="#D1D5DB" />
              <Text style={styles.emptyTitle}>No transactions yet</Text>
              <Text style={styles.emptySubtitle}>Your transaction history will appear here</Text>
            </View>
          }
          ItemSeparatorComponent={() => <View style={{ height: 1, backgroundColor: '#F3F4F6' }} />}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F3F4F6' },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#fff', paddingHorizontal: 16, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  backBtn: { width: 36, height: 36, borderRadius: 8, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 17, fontWeight: '800' },
  row: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', padding: 14, gap: 12 },
  icon: { width: 36, height: 36, borderRadius: 8, justifyContent: 'center', alignItems: 'center' },
  narration: { fontSize: 14, fontWeight: '600', color: '#111827' },
  date: { fontSize: 12, color: '#9CA3AF', marginTop: 2 },
  amount: { fontSize: 14, fontWeight: '700' },
  badge: { marginTop: 4, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4 },
  empty: { alignItems: 'center', paddingTop: 60, gap: 8 },
  emptyTitle: { fontWeight: '700', fontSize: 16, color: '#374151' },
  emptySubtitle: { color: '#9CA3AF', fontSize: 13, textAlign: 'center' },
});
