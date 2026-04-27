import React, { useEffect, useRef, useState, useCallback, useMemo } from 'react';
import {
  SafeAreaView,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  TextInput,
  Animated,
  Easing,
  ScrollView,
  Image,
  KeyboardAvoidingView,
  Platform,
  Keyboard,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { CameraView, Camera } from 'expo-camera';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../services/client';
import { useDialog } from '../context/DialogContext';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';
import { parseQr } from '../utils/qr';

// Scanner viewport height: full while hunting; compact after pay QR captured so the form has room.
const SCANNER_HEIGHT_FULL = 340;
const SCANNER_HEIGHT_PAY_CAPTURED = 200;
const SCAN_LINE_HEIGHT = 2;

const CURRENCY = 'ZMW';
const fmt = (n) => `${CURRENCY} ${Number(n || 0).toFixed(2)}`;

// Minimum time between duplicate scans of the exact same QR string.
// Prevents the camera from firing dozens of events for one code while
// the product card is loading, without slowing down scans of different codes.
const SCAN_DEBOUNCE_MS = 1500;

export default function ScanQrPay({ navigation }) {
  const { alert } = useDialog();
  const { addItem, itemCount: cartCount, totals, refresh: refreshCart } = useCart();
  const { refreshWallet } = useAuth();
  const insets = useSafeAreaInsets();

  const [permission, setPermission] = useState(null);
  const [requestingPermission, setRequestingPermission] = useState(false);

  // High-level mode: "pay" (send money) vs "shop" (scan products into cart).
  const [mode, setMode] = useState('pay');

  // Generic captured state (applies to both payment and unknown payloads).
  const [hasScanned, setHasScanned] = useState(false);
  const [qrPayload, setQrPayload] = useState('');

  /** Recipient preview after a payment QR scan (QR fields + optional API enrich). */
  const [paymentRecipient, setPaymentRecipient] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [keyboardPad, setKeyboardPad] = useState(0);

  // Payment-mode state.
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [paying, setPaying] = useState(false);

  // Shop-mode state.
  const [shopProduct, setShopProduct] = useState(null);  // last scanned product preview
  const [shopBusy, setShopBusy] = useState(false);       // fetching or adding
  const [shopMessage, setShopMessage] = useState(null);  // transient status text

  // Last scan guard (debounces duplicate camera fires).
  const lastScanRef = useRef({ raw: null, at: 0 });

  // Keep the cart badge fresh when this screen becomes visible.
  useFocusEffect(useCallback(() => { refreshCart(); }, [refreshCart]));

  const scannerHeight = useMemo(() => {
    if (mode === 'shop') return SCANNER_HEIGHT_FULL;
    return hasScanned ? SCANNER_HEIGHT_PAY_CAPTURED : SCANNER_HEIGHT_FULL;
  }, [mode, hasScanned]);

  const scanTravel = scannerHeight - SCAN_LINE_HEIGHT - 16;

  /* ── Scan-line animation ─────────────────────────────────────── */
  const scanAnim = useRef(new Animated.Value(0)).current;

  // In "shop" mode we keep the line moving continuously so users know the
  // scanner is live between scans. In "pay" mode we stop once a QR is captured.
  const isScanning =
    permission === true &&
    (mode === 'shop' ? !shopBusy : !hasScanned);

  useEffect(() => {
    if (!isScanning) {
      scanAnim.stopAnimation();
      scanAnim.setValue(0);
      return;
    }
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(scanAnim, { toValue: 1, duration: 1600, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
        Animated.timing(scanAnim, { toValue: 0, duration: 1600, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
      ])
    );
    loop.start();
    return () => loop.stop();
  }, [isScanning, scanAnim]);

  const translateY   = scanAnim.interpolate({ inputRange: [0, 1],       outputRange: [8, 8 + scanTravel] });
  const glowOpacity  = scanAnim.interpolate({ inputRange: [0, 0.5, 1],  outputRange: [0.35, 0.9, 0.35] });

  /* ── Keyboard padding for ScrollView (pairs with KeyboardAvoidingView) ── */
  useEffect(() => {
    const showEvt = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvt = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';
    const subShow = Keyboard.addListener(showEvt, (e) => {
      setKeyboardPad(e.endCoordinates?.height ?? 0);
    });
    const subHide = Keyboard.addListener(hideEvt, () => setKeyboardPad(0));
    return () => {
      subShow.remove();
      subHide.remove();
    };
  }, []);

  /* ── Enrich recipient from server (name / ExtraCash / photo; legacy QRs) ── */
  useEffect(() => {
    if (!qrPayload || !hasScanned || mode !== 'pay') {
      return undefined;
    }
    let cancelled = false;
    setPreviewLoading(true);
    api
      .post('/wallet/payment-qr-preview', { qr_payload: qrPayload })
      .then((res) => {
        if (cancelled) return;
        const r = res.data?.recipient;
        if (!r) return;
        setPaymentRecipient((prev) => ({
          userId: r.id,
          name: (r.name && String(r.name).trim()) || prev?.name || 'ExtraCash user',
          extracashNumber: r.extracash_number ?? prev?.extracashNumber ?? null,
          profilePhotoUrl: r.profile_photo_url ?? prev?.profilePhotoUrl ?? null,
        }));
      })
      .catch(() => { /* keep QR-only preview */ })
      .finally(() => {
        if (!cancelled) setPreviewLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [qrPayload, hasScanned, mode]);

  /* ── Camera permission ───────────────────────────────────────── */
  const requestPermission = async () => {
    setRequestingPermission(true);
    try {
      const { status } = await Camera.requestCameraPermissionsAsync();
      setPermission(status === 'granted');
    } finally {
      setRequestingPermission(false);
    }
  };

  /* ── Scan routing ────────────────────────────────────────────── */
  const routeScan = useCallback(async (rawData) => {
    const now = Date.now();
    if (rawData === lastScanRef.current.raw && now - lastScanRef.current.at < SCAN_DEBOUNCE_MS) {
      return; // duplicate camera event — ignore
    }
    lastScanRef.current = { raw: rawData, at: now };

    const parsed = parseQr(rawData);

    // ── Buy-for-Me QR → sponsor pay flow ──
    // We hand off to the BuyForMe screen which handles the full preview +
    // pay UX. Reset scanner state so re-scanning works cleanly on return.
    if (parsed?.kind === 'buyfor') {
      setHasScanned(false);
      setQrPayload('');
      navigation.navigate('BuyForMe', { token: parsed.token });
      return;
    }

    // ── Product QR → "shop" flow ──
    if (parsed?.kind === 'product') {
      // Flip mode automatically so the UI matches the payload.
      setMode('shop');
      setShopMessage(null);
      setShopBusy(true);

      try {
        const res = await api.get(`/products/${parsed.pid}`);
        const product = res.data;

        if (!product?.is_active || (product.stock ?? 0) < 1) {
          await alert({
            title: 'Unavailable',
            message: 'This product is no longer in stock.',
            tone: 'warn',
          });
          return;
        }

        // Auto-add to cart (reuses existing endpoint; duplicates increment qty).
        const added = await addItem(product.id, 1);
        if (!added.ok) {
          await alert({
            title: 'Could not add to cart',
            message: added.message || 'Please try again.',
            tone: 'danger',
          });
          return;
        }

        setShopProduct(product);
        setShopMessage(`Added "${product.title}"`);
      } catch (err) {
        await alert({
          title: 'Invalid product QR',
          message: err?.response?.data?.message || 'That product could not be found.',
          tone: 'danger',
        });
      } finally {
        setShopBusy(false);
      }
      return;
    }

    // ── Payment QR → "pay" flow ──
    if (parsed?.kind === 'payment') {
      setMode('pay');
      const displayName = (parsed.name && String(parsed.name).trim()) || 'ExtraCash user';
      setPaymentRecipient({
        userId: parsed.uid,
        name: displayName,
        extracashNumber: parsed.extracash_number || null,
        profilePhotoUrl: null,
      });
      setHasScanned(true);
      setQrPayload(rawData || '');
      return;
    }

    // ── Unknown / not one of ours ──
    await alert({
      title: 'Unrecognised QR',
      message: 'This QR code is not an ExtraCash payment or product code.',
      tone: 'warn',
    });
  }, [addItem, alert, navigation]);

  const onScanned = ({ data }) => {
    if (!data) return;
    routeScan(data);
  };

  const resetPaymentScan = () => {
    setHasScanned(false);
    setQrPayload('');
    setPaymentRecipient(null);
    setPreviewLoading(false);
    setAmount('');
    setNote('');
  };

  /* ── Payment submit (unchanged) ──────────────────────────────── */
  const submitPayment = async () => {
    const parsedAmount = Number(amount);
    if (!qrPayload) return alert({ title: 'Missing QR', message: 'Please scan a QR code first.', tone: 'warn' });
    if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
      return alert({ title: 'Invalid amount', message: 'Enter a valid payment amount.', tone: 'warn' });
    }
    setPaying(true);
    try {
      await api.post('/qr-pay', {
        qr_payload: qrPayload,
        amount: parsedAmount,
        note: note.trim() || undefined,
      });
      await alert({
        title: 'Payment successful',
        message: 'Your QR payment has been completed.',
        tone: 'success',
        details: [{ label: 'Amount', value: fmt(parsedAmount) }],
        confirmLabel: 'Done',
      });
      // Reset the scanner so the screen is clean if the user comes back to it,
      // refresh wallet immediately, then jump to the Home tab. Home itself
      // re-pulls wallet + transactions on focus, so the balance/list update.
      resetPaymentScan();
      refreshWallet();
      navigation.navigate('Home');
    } catch (err) {
      const msg = err?.response?.data?.message
        || (err?.request ? 'Cannot reach API server. Check network and try again.' : null)
        || 'Could not complete QR payment.';
      await alert({ title: 'Payment failed', message: msg, tone: 'danger' });
    } finally {
      setPaying(false);
    }
  };

  /* ── Render helpers ──────────────────────────────────────────── */
  const renderPayPanel = () => (
    <View style={styles.panel}>
      <Text style={styles.panelLabel}>QR status</Text>
      <Text style={styles.panelValue}>{qrPayload ? 'Payment QR captured' : 'Point camera at a payment QR'}</Text>

      {qrPayload && paymentRecipient ? (
        <View style={styles.recipientCard}>
          <View style={styles.recipientRow}>
            <View style={styles.recipientAvatar}>
              {paymentRecipient.profilePhotoUrl ? (
                <Image source={{ uri: paymentRecipient.profilePhotoUrl }} style={styles.recipientAvatarImg} resizeMode="cover" />
              ) : (
                <Text style={styles.recipientInitial}>
                  {(paymentRecipient.name || '?').trim().charAt(0).toUpperCase()}
                </Text>
              )}
            </View>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={styles.recipientLabel}>Recipient</Text>
              <Text style={styles.recipientName} numberOfLines={2}>{paymentRecipient.name}</Text>
              {paymentRecipient.extracashNumber ? (
                <Text style={styles.recipientEcn}>ExtraCash No. {paymentRecipient.extracashNumber}</Text>
              ) : previewLoading ? (
                <Text style={styles.recipientEcnMuted}>Looking up ExtraCash number…</Text>
              ) : (
                <Text style={styles.recipientEcnMuted}>ExtraCash number not on this QR — confirm identity in person.</Text>
              )}
            </View>
            {previewLoading ? (
              <ActivityIndicator size="small" color="#6B7280" style={{ alignSelf: 'center' }} />
            ) : null}
          </View>
          <Text style={styles.recipientHint}>Confirm this is the person you intend to pay.</Text>
        </View>
      ) : null}

      <Text style={styles.fieldLabel}>Amount (ZMW)</Text>
      <TextInput
        style={styles.input}
        placeholder="0.00"
        placeholderTextColor="#9CA3AF"
        keyboardType="decimal-pad"
        value={amount}
        onChangeText={setAmount}
      />

      <Text style={styles.fieldLabel}>Note (optional)</Text>
      <TextInput
        style={styles.input}
        placeholder="What is this payment for?"
        placeholderTextColor="#9CA3AF"
        value={note}
        onChangeText={setNote}
      />

      <View style={styles.actionsRow}>
        <TouchableOpacity style={styles.secondaryBtn} onPress={resetPaymentScan}>
          <Text style={styles.secondaryBtnText}>Scan Again</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.primaryBtnInline} onPress={submitPayment} disabled={paying}>
          {paying ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>Pay</Text>}
        </TouchableOpacity>
      </View>
    </View>
  );

  const renderShopPanel = () => (
    <View style={styles.panel}>
      <View style={styles.shopHeadRow}>
        <Text style={styles.panelLabel}>Shop mode</Text>
        <View style={styles.cartPill}>
          <Feather name="shopping-cart" size={12} color="#111827" />
          <Text style={styles.cartPillText}>
            {cartCount} item{cartCount === 1 ? '' : 's'} · {fmt(totals.gross)}
          </Text>
        </View>
      </View>

      {shopBusy ? (
        <View style={styles.shopStatus}>
          <ActivityIndicator size="small" color="#111827" />
          <Text style={styles.shopStatusText}>Looking up product…</Text>
        </View>
      ) : shopProduct ? (
        <View style={styles.shopCard}>
          <View style={styles.shopThumb}>
            {shopProduct.image_url ? (
              <Image source={{ uri: shopProduct.image_url }} style={styles.shopImage} resizeMode="cover" />
            ) : (
              <Feather name="package" size={22} color="#9CA3AF" />
            )}
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.shopCategory} numberOfLines={1}>{shopProduct.category || 'Product'}</Text>
            <Text style={styles.shopTitle} numberOfLines={2}>{shopProduct.title}</Text>
            <Text style={styles.shopPrice}>{fmt(shopProduct.price)}</Text>
            {shopMessage ? <Text style={styles.shopAddedNote}>{shopMessage}</Text> : null}
          </View>
        </View>
      ) : (
        <View style={styles.shopStatus}>
          <Feather name="camera" size={14} color="#6B7280" />
          <Text style={styles.shopStatusText}>Scan a product QR to add it to your cart.</Text>
        </View>
      )}

      <View style={styles.actionsRow}>
        <TouchableOpacity style={styles.secondaryBtn} onPress={() => navigation.navigate('Cart')}>
          <Text style={styles.secondaryBtnText}>View Cart</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.primaryBtnInline, cartCount === 0 && styles.primaryBtnDisabled]}
          onPress={() => navigation.navigate('Checkout')}
          disabled={cartCount === 0}
        >
          <Text style={styles.primaryBtnText}>Checkout</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.iconBtn}>
          <Feather name="arrow-left" size={20} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.title}>{mode === 'shop' ? 'Scan to Shop' : 'Scan QR to Pay'}</Text>
        <View style={{ width: 36 }} />
      </View>

      {/* Mode switcher — users can pre-set intent, but a product QR scan
          will auto-switch into shop mode regardless. */}
      <View style={styles.modeSwitch}>
        <TouchableOpacity
          style={[styles.modeTab, mode === 'pay' && styles.modeTabActive]}
          onPress={() => { setMode('pay'); setShopProduct(null); setShopMessage(null); }}
        >
          <Feather name="send" size={14} color={mode === 'pay' ? '#fff' : '#6B7280'} />
          <Text style={[styles.modeTabText, mode === 'pay' && styles.modeTabTextActive]}>Pay Person</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.modeTab, mode === 'shop' && styles.modeTabActive]}
          onPress={() => {
            setMode('shop');
            setHasScanned(false);
            setQrPayload('');
            setPaymentRecipient(null);
            setPreviewLoading(false);
          }}
        >
          <Feather name="shopping-bag" size={14} color={mode === 'shop' ? '#fff' : '#6B7280'} />
          <Text style={[styles.modeTabText, mode === 'shop' && styles.modeTabTextActive]}>Shop</Text>
        </TouchableOpacity>
      </View>

      {permission === null ? (
        <View style={styles.centerBox}>
          <Text style={styles.infoText}>Camera permission is required to scan QR codes.</Text>
          <TouchableOpacity style={styles.primaryBtn} onPress={requestPermission} disabled={requestingPermission}>
            {requestingPermission ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>Allow Camera</Text>}
          </TouchableOpacity>
        </View>
      ) : permission === false ? (
        <View style={styles.centerBox}>
          <Text style={styles.infoText}>Camera access is blocked. Enable permission and try again.</Text>
          <TouchableOpacity style={styles.primaryBtn} onPress={requestPermission}>
            <Text style={styles.primaryBtnText}>Retry Permission</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          keyboardVerticalOffset={Platform.OS === 'ios' ? insets.top + 100 : 0}
        >
          <ScrollView
            style={{ flex: 1 }}
            contentContainerStyle={[
              styles.content,
              {
                paddingBottom:
                  28 + keyboardPad + Math.max(insets.bottom, 16) + (keyboardPad > 0 ? 12 : 0),
              },
            ]}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
          <View style={[styles.scannerWrap, { height: scannerHeight }]}>
            <CameraView
              // In shop mode we want continuous scanning; the debounce ref
              // makes sure we don't add the same product many times by accident.
              onBarcodeScanned={
                (mode === 'shop' && !shopBusy) || (mode === 'pay' && !hasScanned)
                  ? onScanned
                  : undefined
              }
              barcodeScannerSettings={{
                barcodeTypes: ['qr', 'pdf417', 'ean13', 'ean8', 'code128', 'code39'],
              }}
              style={StyleSheet.absoluteFillObject}
            />

            <View pointerEvents="none" style={styles.scanFrame}>
              <View style={[styles.corner, styles.cornerTL]} />
              <View style={[styles.corner, styles.cornerTR]} />
              <View style={[styles.corner, styles.cornerBL]} />
              <View style={[styles.corner, styles.cornerBR]} />
            </View>

            {isScanning ? (
              <Animated.View
                pointerEvents="none"
                style={[styles.scanLineWrap, { transform: [{ translateY }] }]}
              >
                <Animated.View style={[styles.scanGlow, { opacity: glowOpacity }]} />
                <View style={styles.scanLine} />
              </Animated.View>
            ) : null}

            {/* Overlay badge: in pay mode show "QR captured"; in shop mode
                show a live running cart tally so users keep context. */}
            {mode === 'pay' && hasScanned ? (
              <View pointerEvents="none" style={styles.capturedOverlay}>
                <View style={styles.capturedPill}>
                  <Feather name="check-circle" size={14} color="#fff" />
                  <Text style={styles.capturedText}>Payment QR captured</Text>
                </View>
              </View>
            ) : null}

            {mode === 'shop' && cartCount > 0 ? (
              <View pointerEvents="none" style={styles.capturedOverlay}>
                <View style={[styles.capturedPill, styles.shopOverlayPill]}>
                  <Feather name="shopping-cart" size={14} color="#fff" />
                  <Text style={styles.capturedText}>
                    {cartCount} · {fmt(totals.gross)}
                  </Text>
                </View>
              </View>
            ) : null}
          </View>

          {mode === 'pay' ? renderPayPanel() : renderShopPanel()}
          </ScrollView>
        </KeyboardAvoidingView>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F3F4F6' },
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
  iconBtn: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  title: { fontSize: 17, fontWeight: '800' },

  modeSwitch: {
    flexDirection: 'row',
    gap: 6,
    margin: 16,
    marginBottom: 0,
    backgroundColor: '#E5E7EB',
    borderRadius: 10,
    padding: 4,
  },
  modeTab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 9,
    borderRadius: 8,
  },
  modeTabActive: {
    backgroundColor: '#111827',
  },
  modeTabText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#6B7280',
  },
  modeTabTextActive: {
    color: '#fff',
  },

  centerBox: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 24, gap: 12 },
  infoText:  { fontSize: 14, color: '#374151', textAlign: 'center' },

  content: { padding: 16, gap: 12 },
  scannerWrap: {
    borderRadius: 14,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#D1D5DB',
    backgroundColor: '#000',
  },

  recipientCard: {
    marginTop: 4,
    padding: 12,
    borderRadius: 12,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  recipientRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  recipientAvatar: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#E5E7EB',
    overflow: 'hidden',
    justifyContent: 'center',
    alignItems: 'center',
  },
  recipientAvatarImg: { width: '100%', height: '100%' },
  recipientInitial: { fontSize: 18, fontWeight: '800', color: '#374151' },
  recipientLabel: { fontSize: 11, fontWeight: '800', color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.5 },
  recipientName: { fontSize: 17, fontWeight: '800', color: '#111827', marginTop: 2 },
  recipientEcn: { fontSize: 13, fontWeight: '700', color: '#374151', marginTop: 4 },
  recipientEcnMuted: { fontSize: 12, fontWeight: '600', color: '#9CA3AF', marginTop: 4, fontStyle: 'italic' },
  recipientHint: { fontSize: 11, color: '#6B7280', marginTop: 10, lineHeight: 15 },

  /* scan frame */
  scanFrame: {
    ...StyleSheet.absoluteFillObject,
    margin: 20,
  },
  corner: {
    position: 'absolute',
    width: 26,
    height: 26,
    borderColor: 'rgba(255,255,255,0.9)',
  },
  cornerTL: { top: 0,    left: 0,  borderTopWidth: 3, borderLeftWidth: 3,  borderTopLeftRadius: 6 },
  cornerTR: { top: 0,    right: 0, borderTopWidth: 3, borderRightWidth: 3, borderTopRightRadius: 6 },
  cornerBL: { bottom: 0, left: 0,  borderBottomWidth: 3, borderLeftWidth: 3,  borderBottomLeftRadius: 6 },
  cornerBR: { bottom: 0, right: 0, borderBottomWidth: 3, borderRightWidth: 3, borderBottomRightRadius: 6 },

  /* scan line */
  scanLineWrap: {
    position: 'absolute',
    left: 20,
    right: 20,
    height: SCAN_LINE_HEIGHT + 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scanGlow: {
    position: 'absolute',
    left: 0,
    right: 0,
    height: 22,
    backgroundColor: '#38BDF8',
    borderRadius: 12,
    shadowColor: '#38BDF8',
    shadowOpacity: 0.9,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 0 },
  },
  scanLine: {
    height: SCAN_LINE_HEIGHT,
    alignSelf: 'stretch',
    backgroundColor: '#60A5FA',
    borderRadius: 2,
    shadowColor: '#3B82F6',
    shadowOpacity: 1,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 0 },
  },

  /* overlay pill */
  capturedOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'flex-start',
    alignItems: 'center',
    paddingTop: 16,
  },
  capturedPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(16,185,129,0.95)',
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  shopOverlayPill: { backgroundColor: 'rgba(17,24,39,0.9)' },
  capturedText: { color: '#fff', fontWeight: '800', fontSize: 12 },

  /* panel */
  panel: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 12,
    gap: 8,
  },
  panelLabel: { fontSize: 12, fontWeight: '700', color: '#6B7280' },
  panelValue: { fontSize: 14, fontWeight: '700', color: '#111827' },
  fieldLabel: { fontSize: 12, fontWeight: '800', color: '#111827', marginTop: 6 },
  input: {
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#F9FAFB',
    color: '#111827',
  },
  actionsRow: { flexDirection: 'row', gap: 8, marginTop: 4 },
  secondaryBtn: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#D1D5DB',
    backgroundColor: '#fff',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  secondaryBtnText: { color: '#374151', fontWeight: '700' },
  primaryBtn: {
    backgroundColor: '#111827',
    borderRadius: 10,
    paddingHorizontal: 18,
    paddingVertical: 12,
    minWidth: 140,
    alignItems: 'center',
  },
  primaryBtnInline: {
    flex: 1,
    backgroundColor: '#111827',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  primaryBtnDisabled: { backgroundColor: '#9CA3AF' },
  primaryBtnText: { color: '#fff', fontWeight: '700' },

  /* shop panel extras */
  shopHeadRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  cartPill: {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    backgroundColor: '#F3F4F6',
    paddingHorizontal: 10, paddingVertical: 5,
    borderRadius: 999,
  },
  cartPillText: { fontSize: 11, fontWeight: '800', color: '#111827' },

  shopStatus: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    paddingVertical: 8,
  },
  shopStatusText: { fontSize: 12, color: '#6B7280' },

  shopCard: {
    flexDirection: 'row',
    gap: 10,
    padding: 10,
    borderRadius: 12,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  shopThumb: {
    width: 60, height: 60, borderRadius: 10,
    backgroundColor: '#E5E7EB',
    justifyContent: 'center', alignItems: 'center',
    overflow: 'hidden',
  },
  shopImage: { width: '100%', height: '100%' },
  shopCategory: {
    fontSize: 10, fontWeight: '800',
    color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.5,
  },
  shopTitle: { fontSize: 13, fontWeight: '800', color: '#111827' },
  shopPrice: { marginTop: 2, fontSize: 13, fontWeight: '800', color: '#111827' },
  shopAddedNote: { marginTop: 4, fontSize: 11, color: '#047857', fontWeight: '700' },
});
