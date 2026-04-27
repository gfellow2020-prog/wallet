import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
  Image,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { getMessages, sendMessage, downloadAttachmentToCache } from '../services/messaging';
import { useAuth } from '../context/AuthContext';
import ScreenHeader from '../components/ScreenHeader';
import { compressImageAsset } from '../utils/imageCompression';

export default function MessageThread({ route, navigation }) {
  const { user } = useAuth();
  const insets = useSafeAreaInsets();

  const conversationId = route?.params?.conversationId;
  const otherUser = route?.params?.otherUser || null;
  const context = route?.params?.context || null; // { type:'buy_request'|'product', ... }
  const prefillText = route?.params?.prefillText || null;

  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(null);
  const [sending, setSending] = useState(false);
  const [items, setItems] = useState([]);
  const [text, setText] = useState('');
  const [picked, setPicked] = useState(null);
  const [attachmentUris, setAttachmentUris] = useState({});
  const didPrefillRef = useRef(false);

  const productMarkerForContext = useCallback(() => {
    if (context?.type !== 'product' || !context?.productId) return '';
    // Keep it tiny; UI renders this as a chip and hides the marker text.
    const id = Number(context.productId);
    const img = context?.imageUrl ? encodeURIComponent(String(context.imageUrl)) : '';
    const title = context?.title ? encodeURIComponent(String(context.title)) : '';
    const price = context?.price != null ? encodeURIComponent(String(context.price)) : '';
    // Format: [[EC_PRODUCT:<id>;img=<urlenc>;t=<urlenc>;p=<urlenc>]]
    return `[[EC_PRODUCT:${id};img=${img};t=${title};p=${price}]]`;
  }, [context]);

  const stripProductMarker = useCallback((body) => {
    const raw = String(body || '');
    const match = raw.match(/\[\[EC_PRODUCT:(\d+)(?:;img=([^;\]]*))?(?:;t=([^;\]]*))?(?:;p=([^;\]]*))?\]\]/);
    const imgEnc = match?.[2] || '';
    const tEnc = match?.[3] || '';
    const pEnc = match?.[4] || '';
    return {
      clean: raw.replace(/\s*\[\[EC_PRODUCT:[^\]]+\]\]\s*/g, '').trim(),
      productId: match ? Number(match[1]) : null,
      imageUrl: imgEnc ? decodeURIComponent(imgEnc) : null,
      title: tEnc ? decodeURIComponent(tEnc) : null,
      price: pEnc ? Number(decodeURIComponent(pEnc)) : null,
    };
  }, []);

  const lastId = useMemo(() => (items.length ? items[items.length - 1].id : 0), [items]);
  const pollRef = useRef(null);

  const loadInitial = useCallback(async () => {
    if (!conversationId) return;
    setLoading(true);
    try {
      const msgs = await getMessages(conversationId, { afterId: 0, limit: 100 });
      setItems(msgs);
      setLoadError(null);
    } catch (e) {
      setLoadError(e?.response?.data?.message || e?.message || 'Unable to load this chat.');
    } finally {
      setLoading(false);
    }
  }, [conversationId]);

  const poll = useCallback(async () => {
    if (!conversationId) return;
    try {
      const msgs = await getMessages(conversationId, { afterId: lastId, limit: 50 });
      if (msgs.length) {
        setItems((prev) => [...prev, ...msgs]);
      }
    } catch {
      // silent polling failures
    }
  }, [conversationId, lastId]);

  useEffect(() => {
    loadInitial();
  }, [loadInitial]);

  useEffect(() => {
    // polling while screen is active
    if (!conversationId) return;
    pollRef.current = setInterval(poll, 4000);
    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
      pollRef.current = null;
    };
  }, [conversationId, poll]);

  const hydrateAttachments = useCallback(async (messages) => {
    const token = await AsyncStorage.getItem('token');
    const needed = [];
    for (const m of messages) {
      for (const a of m.attachments || []) {
        if (!attachmentUris[a.id]) needed.push(a.id);
      }
    }
    if (!needed.length) return;

    const updates = {};
    for (const id of needed.slice(0, 8)) {
      try {
        updates[id] = await downloadAttachmentToCache(id, token);
      } catch {}
    }
    if (Object.keys(updates).length) {
      setAttachmentUris((prev) => ({ ...prev, ...updates }));
    }
  }, [attachmentUris]);

  useEffect(() => {
    if (items.length) hydrateAttachments(items);
  }, [items, hydrateAttachments]);

  useEffect(() => {
    if (didPrefillRef.current) return;
    if (!prefillText) return;
    if (text.trim()) return;
    didPrefillRef.current = true;
    setText(String(prefillText));
  }, [prefillText, text]);

  const pickImage = async () => {
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      quality: 0.85,
    });
    if (res.canceled) return;
    const asset = res.assets?.[0] || null;
    if (!asset?.uri) return;
    try {
      const compressed = await compressImageAsset(asset);
      setPicked(compressed);
    } catch {
      setPicked(asset);
    }
  };

  const doSend = async () => {
    if (!conversationId || sending) return;
    const body = text.trim();
    if (!body && !picked?.uri) return;

    setSending(true);
    try {
      const marker = productMarkerForContext();
      const composedBody = marker ? `${body}\n\n${marker}` : body;
      await sendMessage(conversationId, { body: composedBody, imageAsset: picked });
      setText('');
      setPicked(null);
      // Optimistic: let polling pull it, but do a quick poll
      await poll();
    } finally {
      setSending(false);
    }
  };

  const title = otherUser?.name || 'Chat';
  const subtitle = otherUser?.email || '';

  const renderItem = ({ item }) => {
    const mine = item.sender?.id === user?.id;
    const bubble = mine ? styles.bubbleMine : styles.bubbleOther;
    const textStyle = mine ? styles.textMine : styles.textOther;
    const parsed = stripProductMarker(item.body);
    return (
      <View style={[styles.msgRow, { justifyContent: mine ? 'flex-end' : 'flex-start' }]}>
        <View style={[styles.bubble, bubble]}>
          {parsed.clean ? <Text style={[styles.msgText, textStyle]}>{parsed.clean}</Text> : null}
          {parsed.productId ? (
            <TouchableOpacity
              style={[styles.bubbleProductChip, mine ? styles.bubbleProductChipMine : styles.bubbleProductChipOther]}
              activeOpacity={0.85}
              onPress={() => {
                const id = parsed.productId;
                if (!id) return;
                navigation.navigate('ProductDetail', {
                  productId: id,
                  product: {
                    id,
                    title: parsed.title || context?.title,
                    price: parsed.price ?? context?.price,
                    image_url: parsed.imageUrl || context?.imageUrl,
                  },
                });
              }}
            >
              {parsed.imageUrl ? (
                <Image source={{ uri: parsed.imageUrl }} style={styles.bubbleProductThumb} />
              ) : (
                <View style={[styles.bubbleProductThumb, styles.bubbleProductThumbPlaceholder]}>
                  <Feather name="package" size={11} color={mine ? '#fff' : '#111827'} />
                </View>
              )}
              <Text style={[styles.bubbleProductChipText, { color: mine ? '#fff' : '#111827' }]} numberOfLines={1}>
                View product
              </Text>
              <Feather name="chevron-right" size={14} color={mine ? 'rgba(255,255,255,0.75)' : '#6B7280'} />
            </TouchableOpacity>
          ) : null}
          {(item.attachments || []).map((a) => {
            const uri = attachmentUris[a.id];
            if (!uri) {
              return (
                <View key={a.id} style={[styles.imgPlaceholder, mine ? styles.imgPlaceholderMine : styles.imgPlaceholderOther]}>
                  <ActivityIndicator size="small" color={mine ? '#fff' : '#111827'} />
                </View>
              );
            }
            return (
              <Image
                key={a.id}
                source={{ uri }}
                style={styles.img}
                resizeMode="cover"
              />
            );
          })}
          <Text style={[styles.time, mine ? styles.timeMine : styles.timeOther]}>
            {item.created_at ? new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
          </Text>
        </View>
      </View>
    );
  };

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ScreenHeader
        title={title}
        subtitle={subtitle}
        onLeftPress={() => navigation.goBack()}
      />

      {context?.type === 'buy_request' ? (
        <View style={styles.contextBar}>
          <Feather name="gift" size={14} color="#047857" />
          <Text style={styles.contextText} numberOfLines={1}>{context.title}</Text>
        </View>
      ) : context?.type === 'product' ? (
        <View style={styles.contextBarProduct}>
          <TouchableOpacity
            style={styles.productChip}
            activeOpacity={0.85}
            onPress={() => {
              if (context?.productId) {
                navigation.navigate('ProductDetail', {
                  productId: Number(context.productId),
                  product: {
                    id: Number(context.productId),
                    title: context.title,
                    price: context.price,
                    image_url: context.imageUrl,
                  },
                });
              }
            }}
          >
            {context?.imageUrl ? (
              <Image source={{ uri: context.imageUrl }} style={styles.productThumb} />
            ) : (
              <View style={styles.productThumbPlaceholder}>
                <Feather name="package" size={14} color="#6B7280" />
              </View>
            )}
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={styles.productChipTitle} numberOfLines={1}>{context?.title || 'Product'}</Text>
              <Text style={styles.productChipSub} numberOfLines={1}>
                ZMW {Number(context?.price || 0).toFixed(2)}
              </Text>
            </View>
            <Feather name="chevron-right" size={18} color="#6B7280" />
          </TouchableOpacity>
        </View>
      ) : null}

      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={0}
      >
        {loading ? (
          <View style={styles.stateBox}>
            <ActivityIndicator size="large" color="#111827" />
            <Text style={styles.stateText}>Loading chat…</Text>
          </View>
        ) : loadError ? (
          <View style={styles.stateBox}>
            <Text style={styles.stateTitle}>Could not load chat</Text>
            <Text style={styles.stateText}>{loadError}</Text>
            <TouchableOpacity style={styles.retryBtn} onPress={loadInitial} activeOpacity={0.85}>
              <Text style={styles.retryBtnText}>Try again</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <FlatList
            data={items}
            keyExtractor={(m) => String(m.id)}
            renderItem={renderItem}
            contentContainerStyle={{ padding: 14, paddingBottom: 12 }}
            keyboardShouldPersistTaps="handled"
          />
        )}

        {picked?.uri ? (
          <View style={styles.previewRow}>
            <Image source={{ uri: picked.uri }} style={styles.previewImg} />
            <TouchableOpacity onPress={() => setPicked(null)} style={styles.previewClose}>
              <Feather name="x" size={18} color="#111827" />
            </TouchableOpacity>
          </View>
        ) : null}
        {context?.type === 'product' ? (
          <TouchableOpacity
            style={styles.productAttachMini}
            activeOpacity={0.85}
            onPress={() => {
              if (context?.productId) {
                navigation.navigate('ProductDetail', {
                  productId: Number(context.productId),
                  product: {
                    id: Number(context.productId),
                    title: context.title,
                    price: context.price,
                    image_url: context.imageUrl,
                  },
                });
              }
            }}
          >
            <Feather name="tag" size={14} color="#111827" />
            <Text style={styles.productAttachMiniText} numberOfLines={1}>
              {context?.title || 'Product'}
            </Text>
            <Feather name="chevron-right" size={16} color="#6B7280" />
          </TouchableOpacity>
        ) : null}
        <View style={[styles.composer, { paddingBottom: Math.max(insets.bottom, 12) }]}>
          <TouchableOpacity onPress={pickImage} style={styles.iconBtn} activeOpacity={0.8}>
            <Feather name="image" size={18} color="#111827" />
          </TouchableOpacity>
          <TextInput
            value={text}
            onChangeText={setText}
            placeholder="Message…"
            placeholderTextColor="#9CA3AF"
            style={styles.input}
            multiline
          />
          <TouchableOpacity onPress={doSend} style={[styles.sendBtn, sending && { opacity: 0.6 }]} disabled={sending} activeOpacity={0.85}>
            {sending ? <ActivityIndicator size="small" color="#fff" /> : <Feather name="send" size={16} color="#fff" />}
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F9FAFB' },

  contextBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#ECFDF5',
    borderBottomWidth: 1,
    borderBottomColor: '#A7F3D0',
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  contextText: { flex: 1, fontSize: 12, fontWeight: '700', color: '#047857' },
  contextBarProduct: {
    backgroundColor: '#F9FAFB',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  productChip: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#fff',
    borderRadius: 14,
    padding: 10,
  },
  productThumb: { width: 30, height: 30, borderRadius: 10, marginRight: 10, backgroundColor: '#E5E7EB' },
  productThumbPlaceholder: {
    width: 30,
    height: 30,
    borderRadius: 10,
    marginRight: 10,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  productChipTitle: { fontSize: 12, fontWeight: '900', color: '#111827' },
  productChipSub: { marginTop: 2, fontSize: 11, fontWeight: '700', color: '#6B7280' },

  stateBox: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 10 },
  stateTitle: { fontSize: 16, fontWeight: '800', color: '#111827', textAlign: 'center' },
  stateText: { fontSize: 13, color: '#6B7280', textAlign: 'center' },
  retryBtn: { marginTop: 8, backgroundColor: '#111827', paddingHorizontal: 18, paddingVertical: 12, borderRadius: 10 },
  retryBtnText: { color: '#fff', fontWeight: '800' },

  msgRow: { flexDirection: 'row', marginVertical: 6 },
  bubble: { maxWidth: '84%', borderRadius: 16, paddingHorizontal: 12, paddingVertical: 10, gap: 6 },
  bubbleMine: { backgroundColor: '#000' },
  bubbleOther: { backgroundColor: '#E5E7EB' },
  msgText: { fontSize: 14, lineHeight: 19 },
  textMine: { color: '#fff' },
  textOther: { color: '#111827' },
  time: { fontSize: 10 },
  timeMine: { color: 'rgba(255,255,255,0.5)' },
  timeOther: { color: '#6B7280' },

  img: { width: 260, height: 180, borderRadius: 12, backgroundColor: '#D1D5DB' },
  imgPlaceholder: { width: 260, height: 180, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  imgPlaceholderMine: { backgroundColor: 'rgba(255,255,255,0.12)' },
  imgPlaceholderOther: { backgroundColor: '#F3F4F6' },

  bubbleProductChip: {
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  bubbleProductChipMine: { backgroundColor: 'rgba(255,255,255,0.14)' },
  bubbleProductChipOther: { backgroundColor: '#F3F4F6' },
  bubbleProductChipText: { flex: 1, fontSize: 11, fontWeight: '800' },
  bubbleProductThumb: { width: 18, height: 18, borderRadius: 6, backgroundColor: '#E5E7EB' },
  bubbleProductThumbPlaceholder: { backgroundColor: 'rgba(255,255,255,0.14)', alignItems: 'center', justifyContent: 'center' },

  productAttachMini: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F3F4F6',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 12,
    paddingVertical: 8,
    paddingHorizontal: 10,
    marginHorizontal: 14,
    marginTop: 10,
  },
  productAttachMiniText: { flex: 1, marginLeft: 8, marginRight: 8, fontSize: 12, fontWeight: '800', color: '#111827' },

  previewRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingVertical: 10,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
  },
  previewImg: { width: 44, height: 44, borderRadius: 10, backgroundColor: '#E5E7EB' },
  previewClose: { width: 32, height: 32, borderRadius: 10, backgroundColor: '#F3F4F6', alignItems: 'center', justifyContent: 'center' },

  composer: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 8,
    paddingHorizontal: 12,
    paddingTop: 10,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
  },
  iconBtn: {
    width: 38, height: 38, borderRadius: 12,
    backgroundColor: '#F3F4F6',
    alignItems: 'center', justifyContent: 'center',
  },
  input: {
    flex: 1,
    minHeight: 38,
    maxHeight: 120,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#111827',
  },
  sendBtn: {
    width: 44, height: 38, borderRadius: 14,
    backgroundColor: '#111827',
    alignItems: 'center', justifyContent: 'center',
  },
});

