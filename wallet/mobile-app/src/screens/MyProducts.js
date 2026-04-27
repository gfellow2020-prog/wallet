import React, { useEffect, useState, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet,
  ActivityIndicator, RefreshControl, Modal, ScrollView,
  TextInput, KeyboardAvoidingView, Platform, Switch, Animated, Image, Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import QRCode from 'react-native-qrcode-svg';
import * as Location from 'expo-location';
import * as ImagePicker from 'expo-image-picker';
import api from '../services/client';
import { compressImageAsset } from '../utils/imageCompression';

const DEFAULT_CATEGORIES = ['Electronics', 'Clothing', 'Food', 'Furniture', 'Home', 'Sports', 'Books', 'Appliances', 'Other'];
const CONDITIONS = ['new', 'used', 'refurbished'];

/* ─── Reusable custom modals ─────────────────────────────────── */

function SuccessModal({ visible, title, subtitle, detail, onDone }) {
  const scale = React.useRef(new Animated.Value(0.8)).current;
  const opacity = React.useRef(new Animated.Value(0)).current;

  React.useEffect(() => {
    if (visible) {
      Animated.parallel([
        Animated.spring(scale,   { toValue: 1,   useNativeDriver: true, tension: 120, friction: 8 }),
        Animated.timing(opacity, { toValue: 1,   useNativeDriver: true, duration: 180 }),
      ]).start();
    } else {
      scale.setValue(0.8);
      opacity.setValue(0);
    }
  }, [visible]);

  return (
    <Modal visible={visible} transparent animationType="none" onRequestClose={onDone}>
      <View style={sm.overlay}>
        <Animated.View style={[sm.sheet, { transform: [{ scale }], opacity }]}>
          {/* Icon ring */}
          <View style={sm.iconRing}>
            <View style={sm.iconInner}>
              <Feather name="check" size={28} color="#fff" />
            </View>
          </View>

          <Text style={sm.title}>{title}</Text>
          <Text style={sm.subtitle}>{subtitle}</Text>

          {/* Product detail card */}
          {detail && (
            <View style={sm.detailCard}>
              <View style={sm.detailRow}>
                <View style={sm.detailIconBox}>
                  <Feather name="package" size={20} color="#6B7280" />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={sm.detailName} numberOfLines={1}>{detail.title}</Text>
                  <Text style={sm.detailPrice}>ZMW {parseFloat(detail.price).toFixed(2)}</Text>
                </View>
                <View style={sm.liveBadge}>
                  <View style={sm.liveDot} />
                  <Text style={sm.liveText}>Live</Text>
                </View>
              </View>

              <View style={sm.divider} />

              <View style={sm.detailStats}>
                <View style={sm.statItem}>
                  <Feather name="gift" size={13} color="#000" />
                  <Text style={sm.statLabel}>Buyer earns</Text>
                  <Text style={sm.statValue}>ZMW {parseFloat(detail.cashback_amount || 0).toFixed(2)}</Text>
                </View>
                <View style={sm.statDivider} />
                <View style={sm.statItem}>
                  <Feather name="box" size={13} color="#000" />
                  <Text style={sm.statLabel}>In stock</Text>
                  <Text style={sm.statValue}>{detail.stock}</Text>
                </View>
                <View style={sm.statDivider} />
                <View style={sm.statItem}>
                  <Feather name="tag" size={13} color="#000" />
                  <Text style={sm.statLabel}>Category</Text>
                  <Text style={sm.statValue} numberOfLines={1}>{detail.category || '—'}</Text>
                </View>
              </View>
            </View>
          )}

          <TouchableOpacity style={sm.doneBtn} onPress={onDone} activeOpacity={0.85}>
            <Text style={sm.doneBtnText}>Done</Text>
          </TouchableOpacity>

          <TouchableOpacity style={sm.viewAllBtn} onPress={onDone}>
            <Text style={sm.viewAllText}>View My Listings</Text>
          </TouchableOpacity>
        </Animated.View>
      </View>
    </Modal>
  );
}

function ConfirmModal({ visible, title, message, confirmLabel = 'Confirm', danger = false, onConfirm, onCancel }) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCancel}>
      <View style={cm.overlay}>
        <View style={cm.sheet}>
          <View style={[cm.iconBox, danger && cm.iconBoxDanger]}>
            <Feather name={danger ? 'trash-2' : 'alert-circle'} size={22} color={danger ? '#fff' : '#374151'} />
          </View>
          <Text style={cm.title}>{title}</Text>
          <Text style={cm.message}>{message}</Text>
          <View style={cm.btnRow}>
            <TouchableOpacity style={cm.cancelBtn} onPress={onCancel}>
              <Text style={cm.cancelText}>Cancel</Text>
            </TouchableOpacity>
            <TouchableOpacity style={[cm.confirmBtn, danger && cm.confirmDanger]} onPress={onConfirm}>
              <Text style={cm.confirmText}>{confirmLabel}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

function ErrorModal({ visible, message, onClose }) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={cm.overlay}>
        <View style={cm.sheet}>
          <View style={[cm.iconBox, { backgroundColor: '#F3F4F6' }]}>
            <Feather name="alert-circle" size={22} color="#374151" />
          </View>
          <Text style={cm.title}>Something went wrong</Text>
          <Text style={cm.message}>{message}</Text>
          <TouchableOpacity style={[cm.confirmBtn, { marginTop: 4 }]} onPress={onClose}>
            <Text style={cm.confirmText}>OK</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

/* ─── My Listing card ────────────────────────────────────────── */

function MyListingCard({ item, onDelete, onToggle, onEdit, onView }) {
  return (
    <TouchableOpacity style={styles.listingCard} onPress={() => onView(item)} activeOpacity={0.85}>
      <View style={styles.listingImageBox}>
        {item.image_url
          ? <Image source={{ uri: item.image_url }} style={{ width: '100%', height: '100%', borderRadius: 8 }} resizeMode="cover" />
          : <Feather name="package" size={24} color="#D1D5DB" />
        }
      </View>
      <View style={{ flex: 1 }}>
        <Text style={styles.listingTitle} numberOfLines={1}>{item.title}</Text>
        <Text style={styles.listingPrice}>ZMW {parseFloat(item.price).toFixed(2)}</Text>
        <View style={styles.listingMeta}>
          <View style={[styles.listingBadge, { backgroundColor: item.is_active ? '#000' : '#E5E7EB' }]}>
            <Text style={[styles.listingBadgeText, { color: item.is_active ? '#fff' : '#6B7280' }]}>
              {item.is_active ? 'Active' : 'Inactive'}
            </Text>
          </View>
          <Text style={styles.listingStock}>{item.stock} in stock</Text>
          <Text style={styles.listingCashback}>
            <Feather name="gift" size={10} color="#6B7280" /> ZMW {parseFloat(item.cashback_amount || 0).toFixed(2)} cashback
          </Text>
        </View>
      </View>
      <View style={styles.listingActions}>
        <Switch
          value={!!item.is_active}
          onValueChange={() => onToggle(item)}
          trackColor={{ false: '#E5E7EB', true: '#000' }}
          thumbColor="#fff"
          style={{ transform: [{ scaleX: 0.75 }, { scaleY: 0.75 }] }}
        />
        <TouchableOpacity onPress={() => onEdit(item)} style={styles.editBtn}>
          <Feather name="edit-2" size={15} color="#374151" />
        </TouchableOpacity>
        <TouchableOpacity onPress={() => onDelete(item)} style={styles.deleteBtn}>
          <Feather name="trash-2" size={15} color="#9CA3AF" />
        </TouchableOpacity>
      </View>
    </TouchableOpacity>
  );
}

/* ─── Sell form ──────────────────────────────────────────────── */

function SellForm({ onCreated, onClose, categories = DEFAULT_CATEGORIES }) {
  const [form, setForm] = useState({
    title: '', description: '', category: (categories?.[0] || 'Electronics'),
    price: '', condition: 'new', stock: '1',
    location_label: '', latitude: '', longitude: '',
  });
  const [image, setImage]           = useState(null); // { uri, ... }
  const [saving, setSaving]         = useState(false);
  const [gettingLocation, setGettingLocation] = useState(false);
  const [successProduct, setSuccessProduct]   = useState(null);
  const [error, setError]           = useState(null);

  const set = (k, v) => setForm(p => ({ ...p, [k]: v }));

  /* Pick & compress image */
  const pickImage = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      setError('Camera roll permission is required to add a photo.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [4, 3],
      quality: 1,
    });
    if (!result.canceled && result.assets[0]) {
      const asset = result.assets[0];
      try {
        const compressed = await compressImageAsset(asset);
        setImage(compressed);
      } catch {
        setImage({ uri: asset.uri });
      }
    }
  };

  const detectLocation = async () => {
    setGettingLocation(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status === 'granted') {
        const loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
        const rev  = await Location.reverseGeocodeAsync({ latitude: loc.coords.latitude, longitude: loc.coords.longitude });
        const label = rev[0] ? `${rev[0].district || rev[0].city || ''}, ${rev[0].region || ''}`.trim().replace(/^,|,$/g, '') : '';
        set('latitude',  String(loc.coords.latitude));
        set('longitude', String(loc.coords.longitude));
        set('location_label', label || 'My Location');
      }
    } catch {}
    setGettingLocation(false);
  };

  const submit = async () => {
    if (!form.title.trim()) return setError('Please enter a product title.');
    if (!form.price)        return setError('Please enter the price.');
    if (parseFloat(form.price) <= 0) return setError('Price must be greater than 0.');
    if (!image)             return setError('Please add a product photo.');

    setSaving(true);
    try {
      const formData = new FormData();
      formData.append('title',       form.title.trim());
      formData.append('description', form.description.trim() || '');
      formData.append('category',    form.category);
      formData.append('price',       String(parseFloat(form.price)));
      formData.append('condition',   form.condition);
      formData.append('stock',       String(parseInt(form.stock) || 1));
      if (form.location_label) formData.append('location_label', form.location_label);
      if (form.latitude)       formData.append('latitude',  String(form.latitude));
      if (form.longitude)      formData.append('longitude', String(form.longitude));
      formData.append('image', {
        uri:  image.uri,
        type: image.mimeType || 'image/jpeg',
        name: image.fileName || 'product.jpg',
      });

      const res = await api.post('/products', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setSuccessProduct(res.data);
    } catch (e) {
      setError(e?.response?.data?.message || 'Failed to list product. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={{ padding: 20 }} keyboardShouldPersistTaps="handled">
          <Text style={styles.formLabel}>Product Title *</Text>
          <TextInput style={styles.input} value={form.title} onChangeText={v => set('title', v)} placeholder="e.g. Samsung Galaxy A54" />

          <Text style={styles.formLabel}>Description</Text>
          <TextInput style={[styles.input, { height: 80 }]} value={form.description}
            onChangeText={v => set('description', v)} placeholder="Describe your product…"
            multiline textAlignVertical="top" />

          {/* ── Image Picker ── */}
          <Text style={styles.formLabel}>Product Photo *</Text>
          <TouchableOpacity style={styles.imagePicker} onPress={pickImage} activeOpacity={0.8}>
            {image ? (
              <Image source={{ uri: image.uri }} style={{ width: '100%', height: '100%', borderRadius: 10 }} resizeMode="cover" />
            ) : (
              <View style={{ alignItems: 'center', gap: 6 }}>
                <Feather name="camera" size={30} color="#9CA3AF" />
                <Text style={{ color: '#9CA3AF', fontSize: 13, fontWeight: '500' }}>Tap to add a photo</Text>
                <Text style={{ color: '#D1D5DB', fontSize: 11 }}>Auto-compressed for fast loading</Text>
              </View>
            )}
          </TouchableOpacity>

          <Text style={styles.formLabel}>Category</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 14 }}>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              {categories.map(cat => (
                <TouchableOpacity key={cat}
                  style={[styles.chip, form.category === cat && styles.chipActive]}
                  onPress={() => set('category', cat)}>
                  <Text style={[styles.chipText, form.category === cat && styles.chipTextActive]}>{cat}</Text>
                </TouchableOpacity>
              ))}
            </View>
          </ScrollView>

          <View style={{ flexDirection: 'row', gap: 12 }}>
            <View style={{ flex: 1 }}>
              <Text style={styles.formLabel}>Price (ZMW) *</Text>
              <TextInput style={styles.input} value={form.price} onChangeText={v => set('price', v)}
                placeholder="0.00" keyboardType="decimal-pad" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.formLabel}>Stock</Text>
              <TextInput style={styles.input} value={form.stock} onChangeText={v => set('stock', v)}
                placeholder="1" keyboardType="number-pad" />
            </View>
          </View>

          {/* Cashback preview */}
          {!!form.price && parseFloat(form.price) > 0 && (
            <View style={styles.cashbackPreview}>
              <Feather name="gift" size={14} color="#000" />
              <Text style={styles.cashbackPreviewText}>
                Buyer earns ZMW {(parseFloat(form.price) * 0.02).toFixed(2)} cashback (2%)
              </Text>
            </View>
          )}

          <Text style={styles.formLabel}>Condition</Text>
          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 14 }}>
            {CONDITIONS.map(c => (
              <TouchableOpacity key={c}
                style={[styles.chip, form.condition === c && styles.chipActive]}
                onPress={() => set('condition', c)}>
                <Text style={[styles.chipText, form.condition === c && styles.chipTextActive]}>{c}</Text>
              </TouchableOpacity>
            ))}
          </View>

          <Text style={styles.formLabel}>Location</Text>
          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 6 }}>
            <TextInput
              style={[styles.input, { flex: 1, marginBottom: 0 }]}
              value={form.location_label}
              onChangeText={v => set('location_label', v)}
              placeholder="e.g. Woodlands, Lusaka"
            />
            <TouchableOpacity style={styles.gpsBtn} onPress={detectLocation} disabled={gettingLocation}>
              {gettingLocation
                ? <ActivityIndicator size="small" color="#fff" />
                : <Feather name="navigation" size={16} color="#fff" />
              }
            </TouchableOpacity>
          </View>
          <Text style={styles.gpsHint}>Tap the GPS icon to auto-detect your location</Text>

          <TouchableOpacity style={[styles.submitBtn, saving && { opacity: 0.6 }]} onPress={submit} disabled={saving}>
            {saving
              ? <ActivityIndicator color="#fff" />
              : <>
                  <Feather name="check-circle" size={18} color="#fff" />
                  <Text style={styles.submitBtnText}>List Product</Text>
                </>
            }
          </TouchableOpacity>

          <TouchableOpacity style={styles.cancelBtn} onPress={onClose}>
            <Text style={styles.cancelBtnText}>Cancel</Text>
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>

      {/* ── Success modal ── */}
      <SuccessModal
        visible={!!successProduct}
        title="Your listing is live! 🎉"
        subtitle="Buyers near you can now discover and purchase this product."
        detail={successProduct}
        onDone={() => {
          const p = successProduct;
          setSuccessProduct(null);
          // Avoid dismissing nested modals in the same frame (can cause blank sheet on iOS)
          setTimeout(() => onCreated(p), 120);
        }}
      />

      {/* ── Error modal ── */}
      <ErrorModal
        visible={!!error}
        message={error || ''}
        onClose={() => setError(null)}
      />
    </>
  );
}


/* ─── Edit form ──────────────────────────────────────────────── */

function EditForm({ product, onSaved, onClose, categories = DEFAULT_CATEGORIES }) {
  const [form, setForm] = useState({
    title:          product.title || '',
    description:    product.description || '',
    category:       product.category || (categories?.[0] || 'Electronics'),
    price:          String(product.price || ''),
    condition:      product.condition || 'new',
    stock:          String(product.stock || '1'),
    location_label: product.location_label || '',
    latitude:       product.latitude  ? String(product.latitude)  : '',
    longitude:      product.longitude ? String(product.longitude) : '',
  });
  const [image, setImage]           = useState(null); // new image picked; null = keep existing
  const [saving, setSaving]         = useState(false);
  const [gettingLocation, setGettingLocation] = useState(false);
  const [error, setError]           = useState(null);

  const set = (k, v) => setForm(p => ({ ...p, [k]: v }));

  const pickImage = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) { setError('Camera roll permission is required.'); return; }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true, aspect: [4, 3], quality: 1,
    });
    if (!result.canceled && result.assets[0]) {
      const asset = result.assets[0];
      try {
        const compressed = await compressImageAsset(asset);
        setImage(compressed);
      } catch {
        setImage({ uri: asset.uri });
      }
    }
  };

  const detectLocation = async () => {
    setGettingLocation(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status === 'granted') {
        const loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
        const rev  = await Location.reverseGeocodeAsync({ latitude: loc.coords.latitude, longitude: loc.coords.longitude });
        const label = rev[0] ? `${rev[0].district || rev[0].city || ''}, ${rev[0].region || ''}`.trim().replace(/^,|,$/g, '') : '';
        set('latitude',  String(loc.coords.latitude));
        set('longitude', String(loc.coords.longitude));
        set('location_label', label || 'My Location');
      }
    } catch {}
    setGettingLocation(false);
  };

  const submit = async () => {
    if (!form.title.trim()) return setError('Please enter a product title.');
    if (!form.price || parseFloat(form.price) <= 0) return setError('Please enter a valid price.');
    setSaving(true);
    try {
      const formData = new FormData();
      formData.append('title',        form.title.trim());
      formData.append('description',  form.description.trim() || '');
      formData.append('category',     form.category);
      formData.append('price',        String(parseFloat(form.price)));
      formData.append('condition',    form.condition);
      formData.append('stock',        String(parseInt(form.stock) || 1));
      if (form.location_label) formData.append('location_label', form.location_label);
      if (form.latitude)       formData.append('latitude',  String(form.latitude));
      if (form.longitude)      formData.append('longitude', String(form.longitude));
      if (image) {
        formData.append('image', {
          uri: image.uri,
          type: image.mimeType || 'image/jpeg',
          name: image.fileName || 'product.jpg',
        });
      }
      const res = await api.post(`/products/${product.id}/update`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      onSaved(res.data);
    } catch (e) {
      setError(e?.response?.data?.message || 'Failed to save changes. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  const previewUri = image ? image.uri : product.image_url;

  return (
    <>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={{ padding: 20 }} keyboardShouldPersistTaps="handled">

          <Text style={styles.formLabel}>Product Title *</Text>
          <TextInput style={styles.input} value={form.title} onChangeText={v => set('title', v)} placeholder="e.g. Samsung Galaxy A54" />

          <Text style={styles.formLabel}>Description</Text>
          <TextInput style={[styles.input, { height: 80 }]} value={form.description}
            onChangeText={v => set('description', v)} placeholder="Describe your product…"
            multiline textAlignVertical="top" />

          {/* Image picker — shows existing photo if no new one selected */}
          <Text style={styles.formLabel}>Product Photo</Text>
          <TouchableOpacity style={styles.imagePicker} onPress={pickImage} activeOpacity={0.8}>
            {previewUri ? (
              <>
                <Image source={{ uri: previewUri }} style={{ width: '100%', height: '100%', borderRadius: 10 }} resizeMode="cover" />
                <View style={{ position: 'absolute', bottom: 8, right: 8, backgroundColor: 'rgba(0,0,0,0.55)', borderRadius: 6, paddingHorizontal: 8, paddingVertical: 4, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                  <Feather name="camera" size={11} color="#fff" />
                  <Text style={{ color: '#fff', fontSize: 11, fontWeight: '600' }}>Change</Text>
                </View>
              </>
            ) : (
              <View style={{ alignItems: 'center', gap: 6 }}>
                <Feather name="camera" size={30} color="#9CA3AF" />
                <Text style={{ color: '#9CA3AF', fontSize: 13, fontWeight: '500' }}>Tap to add a photo</Text>
              </View>
            )}
          </TouchableOpacity>

          <Text style={styles.formLabel}>Category</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 14 }}>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              {categories.map(cat => (
                <TouchableOpacity key={cat}
                  style={[styles.chip, form.category === cat && styles.chipActive]}
                  onPress={() => set('category', cat)}>
                  <Text style={[styles.chipText, form.category === cat && styles.chipTextActive]}>{cat}</Text>
                </TouchableOpacity>
              ))}
            </View>
          </ScrollView>

          <View style={{ flexDirection: 'row', gap: 12 }}>
            <View style={{ flex: 1 }}>
              <Text style={styles.formLabel}>Price (ZMW) *</Text>
              <TextInput style={styles.input} value={form.price} onChangeText={v => set('price', v)}
                placeholder="0.00" keyboardType="decimal-pad" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.formLabel}>Stock</Text>
              <TextInput style={styles.input} value={form.stock} onChangeText={v => set('stock', v)}
                placeholder="1" keyboardType="number-pad" />
            </View>
          </View>

          {!!form.price && parseFloat(form.price) > 0 && (
            <View style={styles.cashbackPreview}>
              <Feather name="gift" size={14} color="#000" />
              <Text style={styles.cashbackPreviewText}>
                Buyer earns ZMW {(parseFloat(form.price) * 0.02).toFixed(2)} cashback (2%)
              </Text>
            </View>
          )}

          <Text style={styles.formLabel}>Condition</Text>
          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 14 }}>
            {CONDITIONS.map(c => (
              <TouchableOpacity key={c}
                style={[styles.chip, form.condition === c && styles.chipActive]}
                onPress={() => set('condition', c)}>
                <Text style={[styles.chipText, form.condition === c && styles.chipTextActive]}>{c}</Text>
              </TouchableOpacity>
            ))}
          </View>

          <Text style={styles.formLabel}>Location</Text>
          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 6 }}>
            <TextInput
              style={[styles.input, { flex: 1, marginBottom: 0 }]}
              value={form.location_label}
              onChangeText={v => set('location_label', v)}
              placeholder="e.g. Woodlands, Lusaka"
            />
            <TouchableOpacity style={styles.gpsBtn} onPress={detectLocation} disabled={gettingLocation}>
              {gettingLocation
                ? <ActivityIndicator size="small" color="#fff" />
                : <Feather name="navigation" size={16} color="#fff" />
              }
            </TouchableOpacity>
          </View>
          <Text style={styles.gpsHint}>Tap the GPS icon to auto-detect your location</Text>

          <TouchableOpacity style={[styles.submitBtn, saving && { opacity: 0.6 }]} onPress={submit} disabled={saving}>
            {saving
              ? <ActivityIndicator color="#fff" />
              : <>
                  <Feather name="save" size={18} color="#fff" />
                  <Text style={styles.submitBtnText}>Save Changes</Text>
                </>
            }
          </TouchableOpacity>

          <TouchableOpacity style={styles.cancelBtn} onPress={onClose}>
            <Text style={styles.cancelBtnText}>Cancel</Text>
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>

      <ErrorModal visible={!!error} message={error || ''} onClose={() => setError(null)} />
    </>
  );
}


/* ─── Main screen ────────────────────────────────────────────── */

export default function MyProducts({ navigation, route }) {
  const [products, setProducts]     = useState([]);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [categories, setCategories] = useState(DEFAULT_CATEGORIES);
  const [showSell, setShowSell]         = useState(false);
  const [editTarget, setEditTarget]     = useState(null);
  const [viewTarget, setViewTarget]     = useState(null); // detail view
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleteError, setDeleteError]   = useState(null);
  const [loadError, setLoadError]       = useState(null);

  const fetchCategories = useCallback(async () => {
    try {
      const res = await api.get('/categories');
      const list = res?.data?.data || res?.data || [];
      const names = (Array.isArray(list) ? list : [])
        .filter((c) => c && c.is_active !== false)
        .map((c) => String(c.name || '').trim())
        .filter(Boolean);
      setCategories(names.length ? names : DEFAULT_CATEGORIES);
    } catch {
      setCategories(DEFAULT_CATEGORIES);
    }
  }, []);

  const fetchMine = useCallback(async () => {
    try {
      const res = await api.get('/products/mine');
      setProducts(res.data.data || res.data);
      setLoadError(null);
    } catch (e) {
      setLoadError(e?.response?.data?.message || e?.message || 'Unable to load your listings.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchCategories(); fetchMine(); }, [fetchCategories, fetchMine]);

  useEffect(() => {
    if (route?.params?.openSell) {
      setShowSell(true);
      navigation.setParams({ openSell: undefined });
    }
  }, [route?.params?.openSell, navigation]);

  const onRefresh = async () => {
    setRefreshing(true);
    await fetchCategories();
    await fetchMine();
    setRefreshing(false);
  };

  const onDelete = (item) => setDeleteTarget(item);

  const onEdit = (item) => { setViewTarget(null); setEditTarget(item); };

  const onView = (item) => setViewTarget(item);

  const onSaved = (updated) => {
    setEditTarget(null);
    setProducts(p => p.map(x => x.id === updated.id ? updated : x));
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    const id = deleteTarget.id;
    setDeleteTarget(null);
    try {
      await api.delete(`/products/${id}`);
      setProducts(p => p.filter(x => x.id !== id));
    } catch {
      setDeleteError('Could not delete this listing. Please try again.');
    }
  };

  const onToggle = async (item) => {
    try {
      const res = await api.patch(`/products/${item.id}`, { is_active: !item.is_active });
      setProducts(p => p.map(x => x.id === item.id ? res.data : x));
    } catch {
      setDeleteError('Could not update listing status. Please try again.');
    }
  };

  const onCreated = async (product) => {
    setShowSell(false);
    if (product?.id) {
      setProducts(p => [product, ...p.filter(x => x.id !== product.id)]);
    }
    await fetchMine();
  };

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: '#F9FAFB' }}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="chevron-left" size={22} color="#111827" />
        </TouchableOpacity>
        <View style={{ flex: 1 }}>
          <Text style={styles.headerTitle}>My Listings</Text>
          <Text style={styles.headerSub}>Products you're selling</Text>
        </View>
        <TouchableOpacity style={styles.newBtn} onPress={() => setShowSell(true)}>
          <Feather name="plus" size={16} color="#fff" />
          <Text style={styles.newBtnText}>New</Text>
        </TouchableOpacity>
      </View>

      {loading ? (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color="#000" />
        </View>
      ) : loadError ? (
        <View style={styles.empty}>
          <Feather name="alert-circle" size={44} color="#D1D5DB" />
          <Text style={styles.emptyTitle}>Could not load listings</Text>
          <Text style={styles.emptySub}>{loadError}</Text>
          <TouchableOpacity style={styles.listNowBtn} onPress={fetchMine}>
            <Feather name="refresh-cw" size={16} color="#fff" />
            <Text style={styles.listNowText}>Try again</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={products}
          keyExtractor={item => String(item.id)}
          contentContainerStyle={{ padding: 14 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Feather name="shopping-bag" size={44} color="#D1D5DB" />
              <Text style={styles.emptyTitle}>No listings yet</Text>
              <Text style={styles.emptySub}>Start selling and earn on every purchase you make!</Text>
              <TouchableOpacity style={styles.listNowBtn} onPress={() => setShowSell(true)}>
                <Feather name="plus" size={16} color="#fff" />
                <Text style={styles.listNowText}>List Something Now</Text>
              </TouchableOpacity>
            </View>
          }
          renderItem={({ item }) => (
            <MyListingCard item={item} onDelete={onDelete} onToggle={onToggle} onEdit={onEdit} onView={onView} />
          )}
          ListFooterComponent={
            products.length > 0 ? (
              <TouchableOpacity style={styles.addMoreBtn} onPress={() => setShowSell(true)}>
                <Feather name="plus-circle" size={16} color="#000" />
                <Text style={styles.addMoreText}>Add Another Listing</Text>
              </TouchableOpacity>
            ) : null
          }
        />
      )}

      {/* Sell / Create listing modal */}
      <Modal visible={showSell} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setShowSell(false)}>
        <SafeAreaView style={{ flex: 1, backgroundColor: '#fff' }}>
          <View style={styles.modalHeader}>
            <TouchableOpacity onPress={() => setShowSell(false)}>
              <Feather name="x" size={22} color="#111" />
            </TouchableOpacity>
            <Text style={styles.modalTitle}>New Listing</Text>
            <View style={{ width: 22 }} />
          </View>
          <SellForm categories={categories} onCreated={onCreated} onClose={() => setShowSell(false)} />
        </SafeAreaView>
      </Modal>

      {/* ── Item Detail Modal ── */}
      <Modal visible={!!viewTarget} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setViewTarget(null)}>
        {viewTarget && (
          <SafeAreaView style={{ flex: 1, backgroundColor: '#fff' }}>
            {/* Header */}
            <View style={styles.modalHeader}>
              <TouchableOpacity onPress={() => setViewTarget(null)}>
                <Feather name="x" size={22} color="#111" />
              </TouchableOpacity>
              <Text style={styles.modalTitle} numberOfLines={1}>{viewTarget.title}</Text>
              <View style={{ width: 22 }} />
            </View>

            <ScrollView contentContainerStyle={{ padding: 20 }}>
              {/* Hero image */}
              <View style={styles.detailHeroImage}>
                {viewTarget.image_url
                  ? <Image source={{ uri: viewTarget.image_url }} style={{ width: '100%', height: '100%', borderRadius: 12 }} resizeMode="cover" />
                  : <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
                      <Feather name="package" size={64} color="#E5E7EB" />
                    </View>
                }
                {/* Active/Inactive badge over image */}
                <View style={[styles.detailStatusBadge, { backgroundColor: viewTarget.is_active ? '#000' : '#6B7280' }]}>
                  <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: viewTarget.is_active ? '#4ADE80' : '#D1D5DB' }} />
                  <Text style={{ color: '#fff', fontSize: 11, fontWeight: '700' }}>
                    {viewTarget.is_active ? 'Active' : 'Inactive'}
                  </Text>
                </View>
              </View>

              {/* Price row */}
              <View style={{ flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 6 }}>
                <Text style={styles.detailBigPrice}>ZMW {parseFloat(viewTarget.price).toFixed(2)}</Text>
                <View style={{ alignItems: 'flex-end' }}>
                  <Text style={{ fontSize: 11, color: '#9CA3AF' }}>Buyer earns</Text>
                  <Text style={{ fontSize: 14, fontWeight: '800', color: '#000' }}>
                    ZMW {(parseFloat(viewTarget.price) * 0.02).toFixed(2)} cashback
                  </Text>
                </View>
              </View>

              {/* Stats row */}
              <View style={styles.detailStatsRow}>
                <View style={styles.detailStat}>
                  <Feather name="eye" size={15} color="#6B7280" />
                  <Text style={styles.detailStatValue}>{viewTarget.clicks ?? 0}</Text>
                  <Text style={styles.detailStatLabel}>views</Text>
                </View>
                <View style={styles.detailStatDivider} />
                <View style={styles.detailStat}>
                  <Feather name="box" size={15} color="#6B7280" />
                  <Text style={styles.detailStatValue}>{viewTarget.stock}</Text>
                  <Text style={styles.detailStatLabel}>in stock</Text>
                </View>
                <View style={styles.detailStatDivider} />
                <View style={styles.detailStat}>
                  <Feather name="star" size={15} color="#6B7280" />
                  <Text style={styles.detailStatValue}>{viewTarget.condition}</Text>
                  <Text style={styles.detailStatLabel}>condition</Text>
                </View>
                <View style={styles.detailStatDivider} />
                <View style={styles.detailStat}>
                  <Feather name="tag" size={15} color="#6B7280" />
                  <Text style={styles.detailStatValue} numberOfLines={1}>{viewTarget.category}</Text>
                  <Text style={styles.detailStatLabel}>category</Text>
                </View>
              </View>

              {/* Location */}
              {viewTarget.location_label ? (
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 16 }}>
                  <Feather name="map-pin" size={13} color="#9CA3AF" />
                  <Text style={{ fontSize: 13, color: '#6B7280' }}>{viewTarget.location_label}</Text>
                </View>
              ) : null}

              {/* Description */}
              {viewTarget.description ? (
                <>
                  <Text style={styles.detailSectionTitle}>Description</Text>
                  <Text style={styles.detailDescText}>{viewTarget.description}</Text>
                </>
              ) : null}

              {/* Product QR — shareable / printable */}
              {viewTarget.qr_payload ? (
                <>
                  <Text style={styles.detailSectionTitle}>Product QR</Text>
                  <View style={styles.detailQrCard}>
                    <View style={styles.detailQrBox}>
                      <QRCode
                        value={viewTarget.qr_payload}
                        size={160}
                        backgroundColor="#ffffff"
                        color="#111827"
                      />
                    </View>
                    <View style={{ flex: 1, gap: 6 }}>
                      <Text style={styles.detailQrTitle} numberOfLines={2}>{viewTarget.title}</Text>
                      <Text style={styles.detailQrHelp}>
                        Print or stick this on your product. Buyers scan it in the
                        ExtraCash app to add it to their cart and pay instantly.
                      </Text>
                      <TouchableOpacity
                        style={styles.detailQrShareBtn}
                        onPress={async () => {
                          try {
                            await Share.share({
                              title: viewTarget.title,
                              message:
                                `${viewTarget.title} — ZMW ${parseFloat(viewTarget.price).toFixed(2)}\n\n` +
                                `Scan this ExtraCash code to buy in seconds.\n` +
                                `QR: ${viewTarget.qr_payload}`,
                            });
                          } catch {}
                        }}
                      >
                        <Feather name="share-2" size={14} color="#fff" />
                        <Text style={styles.detailQrShareText}>Share QR</Text>
                      </TouchableOpacity>
                    </View>
                  </View>
                </>
              ) : null}

              {/* Cashback note */}
              <View style={styles.detailCashbackNote}>
                <Feather name="gift" size={14} color="#000" />
                <Text style={{ fontSize: 12, color: '#374151', flex: 1 }}>
                  Every sale gives the buyer <Text style={{ fontWeight: '800' }}>2% cashback</Text> and you receive <Text style={{ fontWeight: '800' }}>97%</Text> of the sale price.
                </Text>
              </View>
            </ScrollView>

            {/* Footer actions */}
            <View style={styles.detailFooter}>
              <TouchableOpacity
                style={styles.detailDeleteBtn}
                onPress={() => { setViewTarget(null); setDeleteTarget(viewTarget); }}
              >
                <Feather name="trash-2" size={17} color="#EF4444" />
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.detailEditBtn}
                onPress={() => { setViewTarget(null); setEditTarget(viewTarget); }}
              >
                <Feather name="edit-2" size={17} color="#fff" />
                <Text style={{ color: '#fff', fontWeight: '700', fontSize: 15 }}>Edit Listing</Text>
              </TouchableOpacity>
            </View>
          </SafeAreaView>
        )}
      </Modal>

      {/* Edit listing modal */}
      <Modal visible={!!editTarget} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setEditTarget(null)}>
        <SafeAreaView style={{ flex: 1, backgroundColor: '#fff' }}>
          <View style={styles.modalHeader}>
            <TouchableOpacity onPress={() => setEditTarget(null)}>
              <Feather name="x" size={22} color="#111" />
            </TouchableOpacity>
            <Text style={styles.modalTitle}>Edit Listing</Text>
            <View style={{ width: 22 }} />
          </View>
          {editTarget && <EditForm categories={categories} product={editTarget} onSaved={onSaved} onClose={() => setEditTarget(null)} />}
        </SafeAreaView>
      </Modal>

      {/* Confirm delete modal */}
      <ConfirmModal
        visible={!!deleteTarget}
        title="Delete Listing"
        message={`Remove "${deleteTarget?.title}" from your listings? This cannot be undone.`}
        confirmLabel="Delete"
        danger
        onConfirm={confirmDelete}
        onCancel={() => setDeleteTarget(null)}
      />

      {/* Delete/toggle error modal */}
      <ErrorModal
        visible={!!deleteError}
        message={deleteError || ''}
        onClose={() => setDeleteError(null)}
      />
    </SafeAreaView>
  );
}

/* ─── Styles ─────────────────────────────────────────────────── */

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 14, paddingVertical: 12, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  backBtn: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  headerTitle: { fontSize: 15, fontWeight: '800', color: '#111827' },
  headerSub: { fontSize: 11, color: '#9CA3AF', marginTop: 1 },
  newBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: '#000', paddingHorizontal: 12, paddingVertical: 7, borderRadius: 8 },
  newBtnText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  listingCard: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB', padding: 12, marginBottom: 10 },
  listingImageBox: { width: 56, height: 56, borderRadius: 10, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  listingTitle: { fontSize: 14, fontWeight: '700', color: '#111827', marginBottom: 2 },
  listingPrice: { fontSize: 15, fontWeight: '900', color: '#111', marginBottom: 4 },
  listingMeta: { flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' },
  listingBadge: { paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4 },
  listingBadgeText: { fontSize: 10, fontWeight: '700' },
  listingStock: { fontSize: 11, color: '#6B7280' },
  listingCashback: { fontSize: 10, color: '#6B7280' },
  listingActions: { alignItems: 'center', gap: 4 },
  editBtn: { padding: 6, backgroundColor: '#F3F4F6', borderRadius: 6 },
  deleteBtn: { padding: 6 },

  // Detail modal
  detailHeroImage: { height: 230, backgroundColor: '#F3F4F6', borderRadius: 14, marginBottom: 16, overflow: 'hidden', position: 'relative' },
  detailStatusBadge: { position: 'absolute', top: 10, left: 10, flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 9, paddingVertical: 4, borderRadius: 8 },
  detailBigPrice: { fontSize: 26, fontWeight: '900', color: '#111827' },
  detailStatsRow: { flexDirection: 'row', backgroundColor: '#F9FAFB', borderRadius: 12, padding: 14, marginBottom: 14, alignItems: 'center' },
  detailStat: { flex: 1, alignItems: 'center', gap: 2 },
  detailStatValue: { fontSize: 13, fontWeight: '800', color: '#111827', textTransform: 'capitalize' },
  detailStatLabel: { fontSize: 10, color: '#9CA3AF' },
  detailStatDivider: { width: 1, height: 32, backgroundColor: '#E5E7EB' },
  detailSectionTitle: { fontSize: 13, fontWeight: '700', color: '#374151', marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 },
  detailDescText: { fontSize: 14, color: '#4B5563', lineHeight: 22, marginBottom: 16 },
  detailCashbackNote: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, backgroundColor: '#F9FAFB', borderRadius: 10, padding: 12, marginBottom: 10, borderWidth: 1, borderColor: '#E5E7EB' },
  detailQrCard: {
    flexDirection: 'row',
    gap: 14,
    padding: 14,
    borderRadius: 12,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    marginBottom: 16,
    alignItems: 'center',
  },
  detailQrBox: {
    padding: 8,
    backgroundColor: '#fff',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  detailQrTitle: { fontSize: 13, fontWeight: '800', color: '#111827' },
  detailQrHelp:  { fontSize: 11, color: '#6B7280', lineHeight: 16 },
  detailQrShareBtn: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#111827',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    marginTop: 4,
  },
  detailQrShareText: { color: '#fff', fontWeight: '800', fontSize: 12 },
  detailFooter: { flexDirection: 'row', gap: 10, padding: 16, borderTopWidth: 1, borderTopColor: '#E5E7EB' },
  detailDeleteBtn: { width: 50, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#FCA5A5', backgroundColor: '#FEF2F2', borderRadius: 10 },
  detailEditBtn: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, paddingVertical: 14, borderRadius: 10, backgroundColor: '#000' },
  empty: { padding: 50, alignItems: 'center', gap: 8 },
  emptyTitle: { fontWeight: '800', fontSize: 16, color: '#374151' },
  emptySub: { color: '#9CA3AF', fontSize: 13, textAlign: 'center' },
  listNowBtn: { marginTop: 8, backgroundColor: '#000', paddingHorizontal: 20, paddingVertical: 10, borderRadius: 10, flexDirection: 'row', alignItems: 'center', gap: 6 },
  listNowText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  addMoreBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, padding: 16, borderTopWidth: 1, borderTopColor: '#F3F4F6' },
  addMoreText: { fontWeight: '700', fontSize: 13, color: '#374151' },
  formLabel: { fontSize: 12, fontWeight: '700', color: '#374151', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 6 },
  input: { borderWidth: 1, borderColor: '#E5E7EB', borderRadius: 10, padding: 12, fontSize: 14, color: '#111', backgroundColor: '#fff', marginBottom: 14 },
  chip: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, backgroundColor: '#F3F4F6', borderWidth: 1, borderColor: '#E5E7EB' },
  chipActive: { backgroundColor: '#000', borderColor: '#000' },
  chipText: { fontSize: 12, fontWeight: '600', color: '#6B7280' },
  chipTextActive: { color: '#fff' },
  cashbackPreview: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: '#F9FAFB', borderRadius: 8, padding: 10, marginBottom: 14, borderWidth: 1, borderColor: '#E5E7EB' },
  imagePicker: { height: 165, backgroundColor: '#F9FAFB', borderRadius: 10, borderWidth: 1.5, borderColor: '#E5E7EB', borderStyle: 'dashed', justifyContent: 'center', alignItems: 'center', marginBottom: 14, overflow: 'hidden' },
  cashbackPreviewText: { fontSize: 12, fontWeight: '700', color: '#111' },
  gpsBtn: { width: 46, height: 46, borderRadius: 10, backgroundColor: '#000', justifyContent: 'center', alignItems: 'center' },
  gpsHint: { fontSize: 11, color: '#9CA3AF', marginBottom: 14 },
  submitBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#000', padding: 16, borderRadius: 12, marginTop: 8 },
  submitBtnText: { color: '#fff', fontWeight: '800', fontSize: 15 },
  cancelBtn: { alignItems: 'center', padding: 16 },
  cancelBtnText: { color: '#6B7280', fontWeight: '600', fontSize: 14 },
  modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', padding: 16, borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  modalTitle: { flex: 1, fontSize: 15, fontWeight: '800', color: '#111', textAlign: 'center', marginHorizontal: 8 },
});

/* ─── Success Modal styles ───────────────────────────────────── */
const sm = StyleSheet.create({
  overlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.55)', justifyContent: 'flex-end' },
  sheet: { backgroundColor: '#fff', borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: 28, paddingBottom: 40, alignItems: 'center' },
  iconRing: { width: 80, height: 80, borderRadius: 40, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center', marginBottom: 18 },
  iconInner: { width: 60, height: 60, borderRadius: 30, backgroundColor: '#000', justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 20, fontWeight: '900', color: '#111827', textAlign: 'center', marginBottom: 6 },
  subtitle: { fontSize: 13, color: '#6B7280', textAlign: 'center', lineHeight: 20, marginBottom: 20, paddingHorizontal: 10 },
  detailCard: { width: '100%', borderWidth: 1, borderColor: '#E5E7EB', borderRadius: 16, padding: 16, marginBottom: 22, backgroundColor: '#FAFAFA' },
  detailRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  detailIconBox: { width: 44, height: 44, borderRadius: 10, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  detailName: { fontSize: 14, fontWeight: '800', color: '#111827', marginBottom: 2 },
  detailPrice: { fontSize: 18, fontWeight: '900', color: '#000' },
  liveBadge: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#000', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 20 },
  liveDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#4ADE80' },
  liveText: { color: '#fff', fontSize: 11, fontWeight: '700' },
  divider: { height: 1, backgroundColor: '#E5E7EB', marginVertical: 12 },
  detailStats: { flexDirection: 'row', justifyContent: 'space-around' },
  statItem: { alignItems: 'center', gap: 4, flex: 1 },
  statLabel: { fontSize: 10, color: '#9CA3AF', fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.4 },
  statValue: { fontSize: 13, fontWeight: '800', color: '#111827' },
  statDivider: { width: 1, backgroundColor: '#E5E7EB' },
  doneBtn: { width: '100%', backgroundColor: '#000', borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginBottom: 10 },
  doneBtnText: { color: '#fff', fontWeight: '800', fontSize: 15, letterSpacing: 0.3 },
  viewAllBtn: { paddingVertical: 10 },
  viewAllText: { color: '#6B7280', fontWeight: '600', fontSize: 13 },
});

/* ─── Confirm / Error Modal styles ──────────────────────────── */
const cm = StyleSheet.create({
  overlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', alignItems: 'center', padding: 28 },
  sheet: { width: '100%', backgroundColor: '#fff', borderRadius: 22, padding: 26, alignItems: 'center' },
  iconBox: { width: 52, height: 52, borderRadius: 26, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center', marginBottom: 14 },
  iconBoxDanger: { backgroundColor: '#111827' },
  title: { fontSize: 17, fontWeight: '900', color: '#111827', marginBottom: 6, textAlign: 'center' },
  message: { fontSize: 13, color: '#6B7280', textAlign: 'center', lineHeight: 20, marginBottom: 22 },
  btnRow: { flexDirection: 'row', gap: 10, width: '100%' },
  cancelBtn: { flex: 1, borderWidth: 1, borderColor: '#E5E7EB', borderRadius: 12, paddingVertical: 13, alignItems: 'center' },
  cancelText: { fontWeight: '700', fontSize: 14, color: '#374151' },
  confirmBtn: { flex: 1, backgroundColor: '#000', borderRadius: 12, paddingVertical: 13, alignItems: 'center' },
  confirmDanger: { backgroundColor: '#111827' },
  confirmText: { fontWeight: '800', fontSize: 14, color: '#fff' },
});
