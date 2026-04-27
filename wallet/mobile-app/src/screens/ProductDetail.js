import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  View,
  Text,
  Image,
  ScrollView,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
  Share,
  Modal,
  Pressable,
  Animated,
  Easing,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import QRCode from 'react-native-qrcode-svg';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useDialog } from '../context/DialogContext';
import api from '../services/client';
import ScreenHeader from '../components/ScreenHeader';
import { ensureDirectConversation } from '../services/messaging';
import { optImageUrl } from '../utils/optImage';

const CURRENCY = 'ZMW';

const fmt = (n) => `${CURRENCY} ${Number(n || 0).toFixed(2)}`;

export default function ProductDetail({ route, navigation }) {
  const { wallet } = useAuth();
  const { addItem, itemCount: cartCount, mutating: cartMutating } = useCart();
  const { confirm, alert } = useDialog();
  const insets = useSafeAreaInsets();
  const bottomPad = Math.max(insets.bottom, 16) + 12;
  const initial = route?.params?.product || null;
  const productId = route?.params?.productId || initial?.id;

  const [product, setProduct] = useState(initial);
  const [loading, setLoading] = useState(!initial);
  const [adding, setAdding] = useState(false);
  const [contacting, setContacting] = useState(false);
  const [qrVisible, setQrVisible] = useState(false);

  // "Get it" chooser modal — lets the user pick between buying for themselves
  // (→ Checkout) or asking a friend to pay (→ RequestBuyForMe).
  const [getItVisible, setGetItVisible] = useState(false);

  // Pop-in animation for the QR modal card.
  const qrScale   = useRef(new Animated.Value(0.92)).current;
  const qrOpacity = useRef(new Animated.Value(0)).current;
  useEffect(() => {
    if (qrVisible) {
      Animated.parallel([
        Animated.timing(qrOpacity, { toValue: 1, duration: 160, useNativeDriver: true }),
        Animated.spring(qrScale,   { toValue: 1, useNativeDriver: true, tension: 140, friction: 9 }),
      ]).start();
    } else {
      qrScale.setValue(0.92);
      qrOpacity.setValue(0);
    }
  }, [qrVisible, qrScale, qrOpacity]);

  useEffect(() => {
    (async () => {
      if (!productId) return;
      try {
        const res = await api.get(`/products/${productId}`);
        setProduct(res.data);
      } catch {
        if (!initial) alert({ title: 'Unable to load', message: 'This product could not be loaded.', tone: 'danger' });
      } finally {
        setLoading(false);
      }
    })();
  }, [productId]);

  const balance = Number(wallet?.balance || 0);
  const price = Number(product?.price || 0);
  const cashbackRate = Number(product?.cashback_rate || 0.02);
  const cashback = Number(product?.cashback_amount ?? price * cashbackRate);
  const canAfford = balance >= price && price > 0;
  const walletAfter = useMemo(() => (canAfford ? balance - price + cashback : balance), [canAfford, balance, price, cashback]);
  const shortfall = Math.max(0, price - balance);
  const affordabilityPct = price > 0 ? Math.min(1, balance / price) : 0;

  const handleShareQr = async () => {
    if (!product?.qr_payload) return;
    try {
      await Share.share({
        title: product.title,
        message:
          `${product.title} — ${fmt(price)}\n\n` +
          `Scan this ExtraCash code to add it to your cart and pay instantly.\n` +
          `QR: ${product.qr_payload}`,
      });
    } catch {
      // user dismissed share sheet; nothing to do
    }
  };

  const handleAddToCart = async () => {
    if (!product) return;
    setAdding(true);
    const res = await addItem(product.id, 1);
    setAdding(false);
    if (!res.ok) {
      await alert({
        title: 'Could not add to cart',
        message: res.message || 'Please try again.',
        tone: 'danger',
      });
      return;
    }
    const viewCart = await confirm({
      title: 'Added to cart',
      message: `"${product.title}" has been added to your cart.`,
      tone: 'success',
      confirmLabel: 'View cart',
      cancelLabel: 'Keep shopping',
    });
    if (viewCart) navigation.navigate('Cart');
  };

  /**
   * Get-it chooser → "Buy Now" path.
   * We add the item to the cart and hop straight to Checkout so the user
   * can review their full order (including anything they've already added)
   * before paying. This is the standard marketplace "express checkout" UX.
   */
  const chooseBuyNow = async () => {
    setGetItVisible(false);
    if (!product) return;
    setAdding(true);
    const res = await addItem(product.id, 1);
    setAdding(false);
    if (!res.ok) {
      await alert({
        title: 'Could not add to cart',
        message: res.message || 'Please try again.',
        tone: 'danger',
      });
      return;
    }
    navigation.navigate('Checkout');
  };

  /**
   * Get-it chooser → "Buy for Me" path.
   * Navigate to the dedicated requester page which handles creating the
   * BuyRequest, rendering the QR, sharing the link, and cancelling.
   */
  const chooseBuyForMe = () => {
    setGetItVisible(false);
    navigation.navigate('RequestBuyForMe', {
      productId: product.id,
      product,
    });
  };

  const contactSeller = async () => {
    const seller = product?.seller;
    if (!seller?.id || contacting) return;
    setContacting(true);
    try {
      const conversationId = await ensureDirectConversation(Number(seller.id));
      if (!conversationId) {
        await alert({ title: 'Could not start chat', message: 'Please try again.', tone: 'danger' });
        return;
      }

      navigation.navigate('MessageThread', {
        conversationId,
        otherUser: { id: Number(seller.id), name: seller.name, email: seller.email },
        context: {
          type: 'product',
          productId: Number(product.id),
          title: product.title,
          price: Number(product.price || 0),
          imageUrl: product.image_url || null,
        },
        prefillText: `Hi, I’m interested in your listing: ${product.title}`,
      });
    } catch (e) {
      await alert({
        title: 'Could not start chat',
        message: e?.response?.data?.message || 'Please try again.',
        tone: 'danger',
      });
    } finally {
      setContacting(false);
    }
  };

  if (loading || !product) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#111" />
        <Text style={styles.loadingText}>Loading product…</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      {/* ── Header ── */}
      <ScreenHeader
        title="Product Details"
        leftIcon="chevron-left"
        onLeftPress={() => navigation.goBack()}
        right={(
          <TouchableOpacity style={styles.iconBtn} onPress={() => navigation.navigate('Cart')}>
            <Feather name="shopping-cart" size={18} color="#111827" />
            {cartCount > 0 ? (
              <View style={styles.cartBadge}>
                <Text style={styles.cartBadgeText}>{cartCount > 9 ? '9+' : cartCount}</Text>
              </View>
            ) : null}
          </TouchableOpacity>
        )}
      />

      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={styles.scroll}
        showsVerticalScrollIndicator={false}
      >
        {/* Hero image */}
        <View style={styles.hero}>
          {product.image_url ? (
            <Image source={{ uri: optImageUrl(product.image_url, { w: 1400, q: 65 }) }} style={styles.heroImage} resizeMode="cover" />
          ) : (
            <View style={styles.heroPlaceholder}>
              <Feather name="package" size={64} color="#D1D5DB" />
            </View>
          )}
          {product.condition ? (
            <View style={styles.conditionBadge}>
              <Text style={styles.conditionBadgeText}>{String(product.condition).toUpperCase()}</Text>
            </View>
          ) : null}
        </View>

        {/* Title + meta */}
        <View style={styles.titleBlock}>
          {product.category ? <Text style={styles.category}>{product.category}</Text> : null}
          <Text style={styles.title}>{product.title}</Text>
          <View style={styles.metaRow}>
            <Feather name="map-pin" size={12} color="#6B7280" />
            <Text style={styles.metaText} numberOfLines={1}>
              {product.location_label || 'Nearby vendor'}
              {product.distance_km != null ? ` · ${parseFloat(product.distance_km).toFixed(1)} km` : ''}
            </Text>
          </View>
        </View>

        {/* ── Wallet Math Card ── */}
        <View style={styles.mathCard}>
          <View style={styles.mathHeaderRow}>
            <View style={styles.mathHeaderLeft}>
              <Feather name="credit-card" size={14} color="#6B7280" />
              <Text style={styles.mathHeaderLabel}>Your wallet</Text>
            </View>
            <View style={[styles.statusPill, canAfford ? styles.statusPillOk : styles.statusPillWarn]}>
              <Feather
                name={canAfford ? 'check-circle' : 'alert-circle'}
                size={11}
                color={canAfford ? '#047857' : '#B45309'}
              />
              <Text style={[styles.statusPillText, canAfford ? { color: '#047857' } : { color: '#B45309' }]}>
                {canAfford ? 'You can afford this' : `${fmt(shortfall)} short`}
              </Text>
            </View>
          </View>

          <Text style={styles.balanceBig}>{fmt(balance)}</Text>
          <Text style={styles.balanceSub}>Available balance</Text>

          {/* Affordability bar */}
          <View style={styles.barTrack}>
            <View
              style={[
                styles.barFill,
                {
                  width: `${Math.round(affordabilityPct * 100)}%`,
                  backgroundColor: canAfford ? '#10B981' : '#F59E0B',
                },
              ]}
            />
          </View>
          <View style={styles.barLegendRow}>
            <Text style={styles.barLegend}>{fmt(0)}</Text>
            <Text style={styles.barLegend}>Price {fmt(price)}</Text>
          </View>

          {/* Breakdown */}
          <View style={styles.divider} />

          <View style={styles.row}>
            <View style={styles.rowLeft}>
              <View style={[styles.rowDot, { backgroundColor: '#E5E7EB' }]} />
              <Text style={styles.rowLabel}>Product price</Text>
            </View>
            <Text style={styles.rowValue}>- {fmt(price)}</Text>
          </View>

          <View style={styles.row}>
            <View style={styles.rowLeft}>
              <View style={[styles.rowDot, { backgroundColor: '#A7F3D0' }]} />
              <Text style={styles.rowLabel}>Cashback ({Math.round(cashbackRate * 100)}%)</Text>
            </View>
            <Text style={[styles.rowValue, { color: '#047857' }]}>+ {fmt(cashback)}</Text>
          </View>

          <View style={styles.divider} />

          <View style={styles.row}>
            <Text style={styles.rowTotalLabel}>Wallet after purchase</Text>
            <Text style={[styles.rowTotalValue, canAfford ? { color: '#111827' } : { color: '#9CA3AF' }]}>
              {fmt(walletAfter)}
            </Text>
          </View>

          <View style={styles.earnCallout}>
            <Feather name="gift" size={14} color="#fff" />
            <Text style={styles.earnCalloutText}>
              Buy this and instantly earn <Text style={{ fontWeight: '900' }}>{fmt(cashback)}</Text> cashback
            </Text>
          </View>
        </View>

        {/* Product meta grid */}
        <View style={styles.metaGrid}>
          <View style={styles.metaTile}>
            <Feather name="tag" size={14} color="#6B7280" />
            <Text style={styles.metaTileLabel}>Category</Text>
            <Text style={styles.metaTileValue} numberOfLines={1}>{product.category || 'General'}</Text>
          </View>
          <View style={styles.metaTile}>
            <Feather name="box" size={14} color="#6B7280" />
            <Text style={styles.metaTileLabel}>Stock</Text>
            <Text style={styles.metaTileValue}>{product.stock ?? 1}</Text>
          </View>
          <View style={styles.metaTile}>
            <Feather name="eye" size={14} color="#6B7280" />
            <Text style={styles.metaTileLabel}>Views</Text>
            <Text style={styles.metaTileValue}>{product.clicks ?? 0}</Text>
          </View>
        </View>

        {/* Description */}
        {product.description ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Description</Text>
            <Text style={styles.descText}>{product.description}</Text>
          </View>
        ) : null}

        {/* Seller */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Seller</Text>
          <View style={styles.sellerRow}>
            <View style={styles.sellerAvatar}>
              <Text style={styles.sellerAvatarText}>
                {(product.seller?.name || 'V').slice(0, 2).toUpperCase()}
              </Text>
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.sellerName}>{product.seller?.name || 'Vendor'}</Text>
              <Text style={styles.sellerSub}>Local vendor · 2% cashback on purchase</Text>
            </View>
          </View>
        </View>

        {/* Product QR trigger — opens a modal with the full QR for sharing / printing.
            Keeping this as a compact row keeps the detail page focused on content
            while still making the feature one tap away. */}
        {product.qr_payload ? (
          <TouchableOpacity
            style={styles.qrTrigger}
            onPress={() => setQrVisible(true)}
            activeOpacity={0.85}
          >
            <View style={styles.qrTriggerIcon}>
              <Feather name="maximize" size={18} color="#111827" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.qrTriggerTitle}>Show product QR</Text>
              <Text style={styles.qrTriggerSub}>Tap to view, share, or print. Buyers scan to check out instantly.</Text>
            </View>
            <Feather name="chevron-right" size={18} color="#9CA3AF" />
          </TouchableOpacity>
        ) : null}

        <View style={{ height: 16 }} />
      </ScrollView>

      {/* ── Sticky buy bar — respects bottom safe-area ── */}
      <View style={[styles.footer, { paddingBottom: bottomPad }]}>
        <TouchableOpacity
          style={[styles.addCartBtn, (adding || cartMutating) && styles.addCartBtnDisabled]}
          onPress={handleAddToCart}
          disabled={adding || cartMutating}
          activeOpacity={0.85}
        >
          {adding ? (
            <ActivityIndicator color="#111827" />
          ) : (
            <Feather name="shopping-cart" size={18} color="#111827" />
          )}
        </TouchableOpacity>

        <TouchableOpacity
          style={[
            styles.contactBtn,
            (!product?.seller?.id || contacting) && styles.contactBtnDisabled,
          ]}
          onPress={contactSeller}
          disabled={!product?.seller?.id || contacting}
          activeOpacity={0.85}
        >
          {contacting ? (
            <ActivityIndicator color="#111827" />
          ) : (
            <>
              <Feather name="message-circle" size={16} color="#111827" />
              <Text style={styles.contactBtnText}>Contact seller</Text>
            </>
          )}
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.buyBtn, adding && styles.buyBtnDisabled]}
          onPress={() => setGetItVisible(true)}
          disabled={adding}
          activeOpacity={0.85}
        >
          {adding ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <>
              <Feather name="zap" size={16} color="#fff" />
              <Text style={styles.buyBtnText}>Get it</Text>
            </>
          )}
        </TouchableOpacity>
      </View>

      {/* ── Product QR Modal ─────────────────────────────────────
          Presented as a centred pop-up with scale+fade so it feels
          lightweight (no full-screen takeover) and is easy to dismiss
          via backdrop tap, close button, or hardware back. */}
      <Modal
        visible={qrVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setQrVisible(false)}
      >
        <Pressable style={styles.qrBackdrop} onPress={() => setQrVisible(false)}>
          <Pressable onPress={() => {}}>
            <Animated.View
              style={[
                styles.qrCard,
                { opacity: qrOpacity, transform: [{ scale: qrScale }] },
              ]}
            >
              <View style={styles.qrCardHead}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.qrCardEyebrow}>Product QR</Text>
                  <Text style={styles.qrCardTitle} numberOfLines={2}>{product?.title}</Text>
                </View>
                <TouchableOpacity
                  style={styles.qrCloseBtn}
                  onPress={() => setQrVisible(false)}
                  hitSlop={{ top: 8, right: 8, bottom: 8, left: 8 }}
                >
                  <Feather name="x" size={18} color="#111827" />
                </TouchableOpacity>
              </View>

              <View style={styles.qrCardCode}>
                {product?.qr_payload ? (
                  <QRCode
                    value={product.qr_payload}
                    size={220}
                    backgroundColor="#ffffff"
                    color="#111827"
                  />
                ) : null}
              </View>

              <View style={styles.qrCardMeta}>
                <Text style={styles.qrCardPrice}>{fmt(price)}</Text>
                <Text style={styles.qrCardHelp}>
                  Scan in the ExtraCash app to add this item to your cart and pay instantly.
                </Text>
              </View>

              <View style={styles.qrCardActions}>
                <TouchableOpacity
                  style={styles.qrCardSecondary}
                  onPress={() => setQrVisible(false)}
                >
                  <Text style={styles.qrCardSecondaryText}>Close</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.qrCardPrimary}
                  onPress={handleShareQr}
                  activeOpacity={0.85}
                >
                  <Feather name="share-2" size={15} color="#fff" />
                  <Text style={styles.qrCardPrimaryText}>Share QR</Text>
                </TouchableOpacity>
              </View>
            </Animated.View>
          </Pressable>
        </Pressable>
      </Modal>

      {/* ── "Get it" chooser ─────────────────────────────────────
          Two-option bottom sheet that routes the user to Buy-Now
          (add-to-cart → Checkout) or to the dedicated Buy-for-Me
          requester page. Kept intentionally sparse so the decision
          is immediate and clear. */}
      <Modal
        visible={getItVisible}
        transparent
        presentationStyle="overFullScreen"
        animationType="fade"
        onRequestClose={() => setGetItVisible(false)}
      >
        <Pressable style={styles.giBackdrop} onPress={() => setGetItVisible(false)}>
          <Pressable onPress={() => {}} style={styles.giSheet}>
            <View style={styles.giGrabber} />
            <Text style={styles.giTitle}>How would you like to get it?</Text>
            <Text style={styles.giSubtitle} numberOfLines={2}>
              "{product.title}" · {fmt(price)}
            </Text>

            {/* Option 1 — Buy Now (express checkout) */}
            <TouchableOpacity
              style={styles.giOption}
              onPress={chooseBuyNow}
              activeOpacity={0.85}
              disabled={adding}
            >
              <View style={[styles.giOptionIcon, { backgroundColor: '#111827' }]}>
                <Feather name="zap" size={18} color="#fff" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.giOptionTitle}>Buy Now</Text>
                <Text style={styles.giOptionSub}>
                  Pay from your wallet and get {fmt(cashback)} cashback.
                </Text>
              </View>
              <Feather name="chevron-right" size={18} color="#9CA3AF" />
            </TouchableOpacity>

            {/* Option 2 — Buy for Me (ask a friend) */}
            <TouchableOpacity
              style={styles.giOption}
              onPress={chooseBuyForMe}
              activeOpacity={0.85}
            >
              <View style={[styles.giOptionIcon, { backgroundColor: '#EEF2FF' }]}>
                <Feather name="gift" size={18} color="#4338CA" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.giOptionTitle}>Buy for Me</Text>
                <Text style={styles.giOptionSub}>
                  Ask a friend to cover the payment. Share a QR or link.
                </Text>
              </View>
              <Feather name="chevron-right" size={18} color="#9CA3AF" />
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.giCancel}
              onPress={() => setGetItVisible(false)}
              activeOpacity={0.85}
            >
              <Text style={styles.giCancelText}>Cancel</Text>
            </TouchableOpacity>
          </Pressable>
        </Pressable>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F9FAFB' },
  loadingContainer: { flex: 1, backgroundColor: '#fff', justifyContent: 'center', alignItems: 'center', gap: 10 },
  loadingText: { color: '#6B7280', fontSize: 13 },
  iconBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    position: 'relative',
  },
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

  scroll: { padding: 16, gap: 16 },

  hero: {
    width: '100%',
    height: 240,
    borderRadius: 16,
    backgroundColor: '#F3F4F6',
    overflow: 'hidden',
  },
  heroImage: { width: '100%', height: '100%' },
  heroPlaceholder: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  conditionBadge: {
    position: 'absolute',
    top: 12,
    left: 12,
    backgroundColor: '#000',
    borderRadius: 6,
    paddingHorizontal: 8,
    paddingVertical: 3,
  },
  conditionBadgeText: { color: '#fff', fontSize: 10, fontWeight: '800', letterSpacing: 0.5 },

  titleBlock: { gap: 4 },
  category: {
    fontSize: 10,
    fontWeight: '800',
    color: '#9CA3AF',
    textTransform: 'uppercase',
    letterSpacing: 0.7,
  },
  title: { fontSize: 20, fontWeight: '900', color: '#111827', lineHeight: 26 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 2 },
  metaText: { fontSize: 12, color: '#6B7280', flex: 1 },

  mathCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 16,
  },
  mathHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  mathHeaderLeft: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  mathHeaderLabel: { fontSize: 11, fontWeight: '700', color: '#6B7280', textTransform: 'uppercase', letterSpacing: 0.5 },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
    borderWidth: 1,
  },
  statusPillOk: { backgroundColor: '#ECFDF5', borderColor: '#A7F3D0' },
  statusPillWarn: { backgroundColor: '#FFFBEB', borderColor: '#FCD34D' },
  statusPillText: { fontSize: 11, fontWeight: '700' },

  balanceBig: { fontSize: 28, fontWeight: '900', color: '#111827', marginTop: 2 },
  balanceSub: { fontSize: 11, color: '#9CA3AF', fontWeight: '600', marginBottom: 12 },

  barTrack: {
    width: '100%',
    height: 8,
    borderRadius: 999,
    backgroundColor: '#F3F4F6',
    overflow: 'hidden',
  },
  barFill: { height: '100%', borderRadius: 999 },
  barLegendRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 6 },
  barLegend: { fontSize: 10, color: '#9CA3AF', fontWeight: '600' },

  divider: { height: 1, backgroundColor: '#F3F4F6', marginVertical: 12 },

  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  rowLeft: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  rowDot: { width: 8, height: 8, borderRadius: 4 },
  rowLabel: { fontSize: 13, color: '#374151', fontWeight: '600' },
  rowValue: { fontSize: 13, fontWeight: '700', color: '#111827' },
  rowTotalLabel: { fontSize: 13, fontWeight: '800', color: '#111827' },
  rowTotalValue: { fontSize: 16, fontWeight: '900' },

  earnCallout: {
    marginTop: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#111827',
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
  },
  earnCalloutText: { flex: 1, color: '#fff', fontSize: 12, lineHeight: 18 },

  metaGrid: { flexDirection: 'row', gap: 10 },
  metaTile: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 12,
    gap: 4,
  },
  metaTileLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#9CA3AF',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  metaTileValue: { fontSize: 13, fontWeight: '800', color: '#111827' },

  section: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
    gap: 8,
  },
  sectionTitle: {
    fontSize: 11,
    fontWeight: '800',
    color: '#9CA3AF',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  descText: { fontSize: 13, color: '#374151', lineHeight: 20 },

  sellerRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  sellerAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#000',
    justifyContent: 'center',
    alignItems: 'center',
  },
  sellerAvatarText: { color: '#fff', fontWeight: '800', fontSize: 13 },
  sellerName: { fontSize: 14, fontWeight: '800', color: '#111827' },
  sellerSub: { fontSize: 11, color: '#6B7280', marginTop: 2 },

  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
  },
  addCartBtn: {
    width: 52,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 12,
    paddingVertical: 13,
  },
  addCartBtnDisabled: { opacity: 0.6 },
  contactBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 12,
    paddingVertical: 13,
  },
  contactBtnDisabled: { opacity: 0.6 },
  contactBtnText: { color: '#111827', fontWeight: '800', fontSize: 13, marginLeft: 8 },
  buyBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#111827',
    borderRadius: 12,
    paddingVertical: 13,
  },
  buyBtnDisabled: { backgroundColor: '#9CA3AF' },
  buyBtnText: { color: '#fff', fontWeight: '800', fontSize: 13 },

  /* Inline trigger row */
  qrTrigger: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginHorizontal: 16,
    marginTop: 12,
    padding: 14,
    borderRadius: 14,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  qrTriggerIcon: {
    width: 40, height: 40, borderRadius: 10,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
  },
  qrTriggerTitle: { fontSize: 14, fontWeight: '800', color: '#111827' },
  qrTriggerSub:   { fontSize: 11, color: '#6B7280', marginTop: 2 },

  /* Modal */
  qrBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.55)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  qrCard: {
    width: '100%',
    maxWidth: 360,
    backgroundColor: '#fff',
    borderRadius: 22,
    padding: 20,
    gap: 16,
    shadowColor: '#000',
    shadowOpacity: 0.2,
    shadowRadius: 24,
    shadowOffset: { width: 0, height: 12 },
    elevation: 12,
  },
  qrCardHead: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  qrCardEyebrow: {
    fontSize: 10, fontWeight: '800', color: '#9CA3AF',
    textTransform: 'uppercase', letterSpacing: 0.6,
  },
  qrCardTitle: { fontSize: 16, fontWeight: '900', color: '#111827', marginTop: 2 },
  qrCloseBtn: {
    width: 32, height: 32, borderRadius: 16,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
  },
  qrCardCode: {
    alignItems: 'center',
    padding: 18,
    borderRadius: 18,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  qrCardMeta: { alignItems: 'center', gap: 4 },
  qrCardPrice: { fontSize: 20, fontWeight: '900', color: '#111827' },
  qrCardHelp:  { fontSize: 12, color: '#6B7280', textAlign: 'center', lineHeight: 18 },
  qrCardActions: { flexDirection: 'row', gap: 10 },
  qrCardSecondary: {
    flex: 1,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
    backgroundColor: '#F3F4F6',
  },
  qrCardSecondaryText: { fontSize: 14, fontWeight: '800', color: '#111827' },
  qrCardPrimary: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderRadius: 12,
    paddingVertical: 12,
    backgroundColor: '#111827',
  },
  qrCardPrimaryText: { fontSize: 14, fontWeight: '800', color: '#fff' },

  /* ── "Get it" chooser bottom sheet ─────────────────────────── */
  giBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(15,23,42,0.55)',
    justifyContent: 'flex-end',
  },
  giSheet: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 24,
    gap: 10,
  },
  giGrabber: {
    width: 44, height: 4, borderRadius: 2,
    backgroundColor: '#E5E7EB',
    alignSelf: 'center',
    marginBottom: 10,
  },
  giTitle: {
    fontSize: 18, fontWeight: '900', color: '#111827',
  },
  giSubtitle: {
    fontSize: 12, color: '#6B7280', marginBottom: 8,
  },
  giOption: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 14,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  giOptionIcon: {
    width: 40, height: 40, borderRadius: 10,
    justifyContent: 'center', alignItems: 'center',
  },
  giOptionTitle: {
    fontSize: 14, fontWeight: '800', color: '#111827',
  },
  giOptionSub: {
    fontSize: 12, color: '#6B7280', marginTop: 2,
  },
  giCancel: {
    marginTop: 6,
    alignItems: 'center',
    paddingVertical: 12,
  },
  giCancelText: {
    fontSize: 14, fontWeight: '700', color: '#6B7280',
  },
});
