import React, { useCallback, useState } from 'react';
import { View, Text, FlatList, StyleSheet, TouchableOpacity, ActivityIndicator, RefreshControl, Modal, TextInput, Keyboard } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import ScreenHeader from '../components/ScreenHeader';
import api from '../services/client';
import { ensureDirectConversation, listConversations } from '../services/messaging';
import { useAuth } from '../context/AuthContext';

export default function Messages({ navigation }) {
  const { user } = useAuth();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);
  const [newChatOpen, setNewChatOpen] = useState(false);
  const [lookupInput, setLookupInput] = useState('');
  const [lookupState, setLookupState] = useState('idle'); // idle | searching | found | notfound
  const [lookupUser, setLookupUser] = useState(null);
  const [lookupError, setLookupError] = useState(null);

  const load = useCallback(async ({ isRefresh = false } = {}) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    try {
      const rows = await listConversations();
      setItems(rows);
      setError(null);
    } catch (e) {
      setError(e?.response?.data?.message || e?.message || 'Unable to load messages.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const openNewChat = () => {
    setLookupInput('');
    setLookupState('idle');
    setLookupUser(null);
    setLookupError(null);
    setNewChatOpen(true);
  };

  const closeNewChat = () => {
    Keyboard.dismiss();
    setNewChatOpen(false);
  };

  const runLookup = async () => {
    const trimmed = (lookupInput || '').trim();
    if (!trimmed) return;
    setLookupState('searching');
    setLookupError(null);
    setLookupUser(null);
    try {
      const res = await api.post('/users/lookup', { extracash_number: trimmed });
      setLookupUser(res.data);
      setLookupState('found');
    } catch (err) {
      setLookupError(err?.response?.data?.message || 'We couldn’t find that ExtraCash number.');
      setLookupState('notfound');
    }
  };

  const startChat = async () => {
    if (!lookupUser?.id) return;
    try {
      const conversationId = await ensureDirectConversation(lookupUser.id);
      if (conversationId) {
        closeNewChat();
        navigation.navigate('MessageThread', {
          conversationId,
          otherUser: { id: lookupUser.id, name: lookupUser.name, email: lookupUser.email },
        });
      }
    } catch (err) {
      setLookupError(err?.response?.data?.message || 'Could not start chat right now.');
      setLookupState('notfound');
    }
  };

  const openThread = (c) => {
    navigation.navigate('MessageThread', {
      conversationId: c.id,
      otherUser: c.other_user || null,
    });
  };

  const renderItem = ({ item }) => {
    const other = item.other_user || {};
    const initials = (other.name || 'U').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
    return (
      <TouchableOpacity style={styles.row} onPress={() => openThread(item)} activeOpacity={0.85}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{initials}</Text>
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.name} numberOfLines={1}>{other.name || 'User'}</Text>
          <Text style={styles.sub} numberOfLines={1}>{other.email || ' '}</Text>
        </View>
        <Feather name="chevron-right" size={18} color="#9CA3AF" />
      </TouchableOpacity>
    );
  };

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ScreenHeader
        title="Messages"
        onLeftPress={() => navigation.goBack()}
        right={(
          <TouchableOpacity style={styles.newBtn} onPress={openNewChat} activeOpacity={0.8} accessibilityLabel="New chat">
            <Feather name="plus" size={18} color="#111827" />
          </TouchableOpacity>
        )}
      />

      {loading ? (
        <View style={styles.stateBox}>
          <ActivityIndicator size="large" color="#111827" />
          <Text style={styles.stateText}>Loading…</Text>
        </View>
      ) : error ? (
        <View style={styles.stateBox}>
          <Text style={styles.stateTitle}>Could not load messages</Text>
          <Text style={styles.stateText}>{error}</Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => load()}>
            <Text style={styles.retryBtnText}>Try again</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(i) => String(i.id)}
          renderItem={renderItem}
          contentContainerStyle={{ padding: 16, gap: 12 }}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => load({ isRefresh: true })}
              tintColor="#111827"
            />
          }
          ListEmptyComponent={
            <View style={styles.stateBox}>
              <View style={[styles.stateIcon, { backgroundColor: '#F3F4F6' }]}>
                <Feather name="message-circle" size={22} color="#6B7280" />
              </View>
              <Text style={styles.stateTitle}>No conversations yet</Text>
              <Text style={styles.stateText}>Start a Buy-for-Me request or message someone from their profile.</Text>
            </View>
          }
        />
      )}

      <Modal visible={newChatOpen} transparent animationType="fade" onRequestClose={closeNewChat}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>New chat</Text>
              <TouchableOpacity onPress={closeNewChat} style={styles.modalClose} activeOpacity={0.8}>
                <Feather name="x" size={18} color="#111827" />
              </TouchableOpacity>
            </View>

            <Text style={styles.modalHint}>Search by ExtraCash Number (user code).</Text>
            <View style={styles.lookupRow}>
              <View style={styles.lookupPrefix}>
                <Text style={styles.lookupPrefixText}>EC</Text>
              </View>
              <TextInput
                value={lookupInput}
                onChangeText={(t) => { setLookupInput(t); if (lookupState !== 'idle') setLookupState('idle'); }}
                placeholder="Enter number"
                placeholderTextColor="#9CA3AF"
                style={styles.lookupInput}
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="number-pad"
                returnKeyType="search"
                onSubmitEditing={runLookup}
              />
              <TouchableOpacity
                style={[styles.lookupBtn, (!lookupInput.trim() || lookupState === 'searching') && styles.lookupBtnDisabled]}
                onPress={runLookup}
                disabled={!lookupInput.trim() || lookupState === 'searching'}
                activeOpacity={0.85}
              >
                {lookupState === 'searching'
                  ? <ActivityIndicator size="small" color="#fff" />
                  : <Text style={styles.lookupBtnText}>Lookup</Text>
                }
              </TouchableOpacity>
            </View>

            {lookupState === 'notfound' && lookupError ? (
              <View style={styles.lookupErrorRow}>
                <Feather name="alert-triangle" size={14} color="#92400E" />
                <Text style={styles.lookupErrorText}>{lookupError}</Text>
              </View>
            ) : null}

            {lookupState === 'found' && lookupUser ? (
              <View style={styles.resolvedCard}>
                <View style={styles.resolvedAvatar}>
                  <Text style={styles.resolvedAvatarText}>
                    {(lookupUser.name || 'U').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()}
                  </Text>
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={styles.resolvedName} numberOfLines={1}>{lookupUser.name || 'User'}</Text>
                  <Text style={styles.resolvedSub} numberOfLines={1}>EC · {lookupUser.extracash_number}</Text>
                </View>
                <TouchableOpacity style={styles.startBtn} onPress={startChat} activeOpacity={0.85}>
                  <Feather name="message-circle" size={14} color="#fff" />
                  <Text style={styles.startBtnText}>Chat</Text>
                </TouchableOpacity>
              </View>
            ) : null}
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F9FAFB' },
  newBtn: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },

  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
  },
  avatar: {
    width: 40, height: 40, borderRadius: 20,
    backgroundColor: '#111827',
    justifyContent: 'center', alignItems: 'center',
  },
  avatarText: { color: '#fff', fontWeight: '800', fontSize: 13 },
  name: { fontSize: 14, fontWeight: '800', color: '#111827' },
  sub: { fontSize: 11, color: '#6B7280', marginTop: 2 },

  stateBox: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 10 },
  stateIcon: { width: 52, height: 52, borderRadius: 26, justifyContent: 'center', alignItems: 'center' },
  stateTitle: { fontSize: 16, fontWeight: '800', color: '#111827', textAlign: 'center' },
  stateText: { fontSize: 13, color: '#6B7280', textAlign: 'center' },
  retryBtn: { marginTop: 8, backgroundColor: '#111827', paddingHorizontal: 18, paddingVertical: 12, borderRadius: 10 },
  retryBtnText: { color: '#fff', fontWeight: '800' },

  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.35)', justifyContent: 'center', padding: 18 },
  modalCard: { backgroundColor: '#fff', borderRadius: 16, padding: 14, borderWidth: 1, borderColor: '#E5E7EB' },
  modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  modalTitle: { fontSize: 16, fontWeight: '900', color: '#111827' },
  modalClose: { width: 32, height: 32, borderRadius: 10, backgroundColor: '#F3F4F6', alignItems: 'center', justifyContent: 'center' },
  modalHint: { marginTop: 6, fontSize: 12, color: '#6B7280', fontWeight: '600' },

  lookupRow: { flexDirection: 'row', alignItems: 'center', marginTop: 12, gap: 10 },
  lookupPrefix: { width: 44, height: 46, borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB', backgroundColor: '#F9FAFB', alignItems: 'center', justifyContent: 'center' },
  lookupPrefixText: { fontWeight: '900', color: '#6B7280' },
  lookupInput: { flex: 1, height: 46, borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB', backgroundColor: '#F9FAFB', paddingHorizontal: 12, color: '#111827', fontWeight: '700' },
  lookupBtn: { height: 46, paddingHorizontal: 14, borderRadius: 12, backgroundColor: '#111827', alignItems: 'center', justifyContent: 'center' },
  lookupBtnDisabled: { backgroundColor: '#9CA3AF' },
  lookupBtnText: { color: '#fff', fontWeight: '900', fontSize: 13 },

  lookupErrorRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 10, backgroundColor: '#FFFBEB', borderWidth: 1, borderColor: '#FCD34D', padding: 10, borderRadius: 12 },
  lookupErrorText: { flex: 1, fontSize: 12, fontWeight: '700', color: '#92400E' },

  resolvedCard: { flexDirection: 'row', alignItems: 'center', gap: 12, marginTop: 12, borderRadius: 14, borderWidth: 1, borderColor: '#E5E7EB', backgroundColor: '#fff', padding: 12 },
  resolvedAvatar: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#111827', alignItems: 'center', justifyContent: 'center' },
  resolvedAvatarText: { color: '#fff', fontWeight: '900' },
  resolvedName: { fontSize: 13, fontWeight: '900', color: '#111827' },
  resolvedSub: { marginTop: 2, fontSize: 11, color: '#6B7280', fontWeight: '700' },
  startBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: '#111827', paddingHorizontal: 12, paddingVertical: 10, borderRadius: 999 },
  startBtnText: { color: '#fff', fontWeight: '900', fontSize: 12 },
});

