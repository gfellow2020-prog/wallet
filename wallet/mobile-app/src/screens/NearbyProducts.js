import React, { useEffect, useState, useCallback, useRef } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet,
  ActivityIndicator, RefreshControl, TextInput, Image,
  Modal, ScrollView, KeyboardAvoidingView, Platform, PanResponder,
  Pressable,
  Animated,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import * as Location from 'expo-location';
import api from '../services/client';
import { useCart } from '../context/CartContext';
import { useDialog } from '../context/DialogContext';
import { ensureDirectConversation } from '../services/messaging';
import BottomTabsOverlay from '../components/BottomTabsOverlay';
import { optImageUrl } from '../utils/optImage';

// Categories are now fetched dynamically from the backend (active only).
// We keep the "All" pill client-side as a virtual category.
const DEFAULT_CATEGORIES = [{ name: 'All', slug: 'all' }];

const CONDITION_COLOR = { new: '#000', used: '#525252', refurbished: '#737373' };

function ProductCard({ item, onPress, featured = false }) {
  const cashbackPct = item.cashback_rate ? (item.cashback_rate * 100).toFixed(0) : '2';
  return (
    <TouchableOpacity
      style={[styles.card, featured && styles.cardFeatured]}
      onPress={() => onPress(item)}
      activeOpacity={0.85}
    >
      {/* Image / Placeholder */}
      <View style={[styles.cardImage, featured && styles.cardImageFeatured]}>
        {item.image_url
          ? <Image source={{ uri: optImageUrl(item.image_url, { w: featured ? 1200 : 900, q: 65 }) }} style={{ width: '100%', height: '100%', borderRadius: 10 }} resizeMode="cover" />
          : <View style={styles.cardImagePlaceholder}>
              <Feather name="package" size={featured ? 48 : 28} color="#D1D5DB" />
            </View>
        }
        {/* Condition badge */}
        <View style={[styles.conditionBadge, { backgroundColor: CONDITION_COLOR[item.condition] || '#000' }]}>
          <Text style={styles.conditionText}>{item.condition}</Text>
        </View>
        {/* Click count badge (top-right) */}
        {item.clicks > 0 && (
          <View style={styles.clicksBadge}>
            <Feather name="eye" size={9} color="#fff" />
            <Text style={styles.clicksBadgeText}>{item.clicks >= 1000 ? `${(item.clicks / 1000).toFixed(1)}k` : item.clicks}</Text>
          </View>
        )}
        {/* Featured label */}
        {featured && (
          <View style={styles.featuredBadge}>
            <Feather name="trending-up" size={10} color="#fff" />
            <Text style={styles.featuredBadgeText}>Most Popular</Text>
          </View>
        )}
      </View>

      <View style={[styles.cardBody, featured && styles.cardBodyFeatured]}>
        <Text style={styles.cardCategory}>{item.category}</Text>
        <Text style={[styles.cardTitle, featured && styles.cardTitleFeatured]} numberOfLines={featured ? 1 : 2}>{item.title}</Text>

        <View style={styles.cardMeta}>
          <Feather name="map-pin" size={11} color="#9CA3AF" />
          <Text style={styles.cardLocation} numberOfLines={1}>
            {item.location_label || 'Nearby'}
            {item.distance_km != null ? ` · ${parseFloat(item.distance_km).toFixed(1)} km` : ''}
          </Text>
        </View>

        {/* Clicks row — hidden on featured to save space */}
        {!featured && (
          <View style={styles.clicksRow}>
            <Feather name="eye" size={10} color="#6B7280" />
            <Text style={styles.clicksText}>{item.clicks ?? 0} {(item.clicks ?? 0) === 1 ? 'view' : 'views'}</Text>
          </View>
        )}

        {/* Price row */}
        <View style={styles.priceRow}>
          <Text style={[styles.price, featured && styles.priceFeatured]}>ZMW {parseFloat(item.price).toFixed(2)}</Text>
          {/* Cashback pill */}
          <View style={styles.cashbackPill}>
            <Feather name="gift" size={11} color="#fff" />
            <Text style={styles.cashbackText}>Earn ZMW {parseFloat(item.cashback_amount || 0).toFixed(2)}</Text>
          </View>
        </View>

        {!featured && (
          <Text style={styles.cashbackSub}>
            {cashbackPct}% cashback on this purchase
          </Text>
        )}
      </View>
    </TouchableOpacity>
  );
}

function PromoCard({ item, onPress }) {
  // Use an existing product as the promo payload, but style it like a full-width “ad”.
  const cashbackPct = item?.cashback_rate ? (item.cashback_rate * 100).toFixed(0) : '2';
  return (
    <TouchableOpacity
      style={styles.promoCard}
      activeOpacity={0.9}
      onPress={() => item?.id && onPress?.(item)}
      accessibilityLabel="Promoted product"
    >
      <View style={styles.promoImage}>
        {item?.image_url ? (
          <Image
            source={{ uri: optImageUrl(item.image_url, { w: 1400, q: 65 }) }}
            style={{ width: '100%', height: '100%', borderRadius: 16 }}
            resizeMode="cover"
          />
        ) : (
          <View style={styles.cardImagePlaceholder}>
            <Feather name="package" size={36} color="#D1D5DB" />
          </View>
        )}

        <View style={styles.promoBadgeLeft}>
          <Text style={styles.promoBadgeText}>{String(item?.condition || 'USED').toUpperCase()}</Text>
        </View>
        <View style={styles.promoBadgeRight}>
          <Text style={styles.promoBadgeRightText}>PROMOTED</Text>
        </View>
      </View>

      <View style={styles.promoBody}>
        <Text style={styles.promoCategory}>{String(item?.category || 'Marketplace').toUpperCase()}</Text>
        <Text style={styles.promoTitle} numberOfLines={2}>{item?.title || 'Promoted listing'}</Text>

        <View style={styles.cardMeta}>
          <Feather name="map-pin" size={11} color="#9CA3AF" />
          <Text style={styles.cardLocation} numberOfLines={1}>
            {item?.location_label || 'Nearby'}
            {item?.distance_km != null ? ` · ${parseFloat(item.distance_km).toFixed(1)} km` : ''}
          </Text>
        </View>

        <View style={styles.promoPriceRow}>
          <Text style={styles.promoPrice}>ZMW {parseFloat(item?.price || 0).toFixed(2)}</Text>
          <View style={styles.cashbackPill}>
            <Feather name="gift" size={11} color="#fff" />
            <Text style={styles.cashbackText}>Earn ZMW {parseFloat(item?.cashback_amount || 0).toFixed(2)}</Text>
          </View>
        </View>
        <Text style={styles.cashbackSub}>{cashbackPct}% cashback on this purchase</Text>
      </View>
    </TouchableOpacity>
  );
}

export default function NearbyProducts({ navigation }) {
  const insets = useSafeAreaInsets();
  const { addItem, itemCount: cartCount, mutating: cartMutating } = useCart();
  const { confirm, alert } = useDialog();
  const [products, setProducts]     = useState([]);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [page, setPage]             = useState(1);
  const [hasMore, setHasMore]       = useState(true);
  const [category, setCategory]     = useState('All');
  const [categories, setCategories] = useState(DEFAULT_CATEGORIES);
  const [location, setLocation]     = useState(null);
  const [selected, setSelected]     = useState(null); // detail modal
  const [buying, setBuying]         = useState(false); // purchase in progress
  const [addingToCart, setAddingToCart] = useState(false);
  const [contactingSeller, setContactingSeller] = useState(false);
  const [error, setError] = useState(null);

  // "Get it" chooser — same UX as the standalone ProductDetail page so
  // tapping the primary action always routes through Buy-Now or Buy-for-Me.
  // We stash the product the user tapped so the chooser keeps working even
  // after we dismiss the detail modal (to avoid iOS modal-on-modal glitches).
  const [getItVisible, setGetItVisible] = useState(false);
  const [getItProduct, setGetItProduct] = useState(null);
  const [comments, setComments]     = useState([]);
  const [commentText, setCommentText] = useState('');
  const [sendingComment, setSendingComment] = useState(false);
  const [expandedGroups, setExpandedGroups] = useState({}); // key: groupIndex → bool
  const [likeState, setLikeState]   = useState({ liked: false, like_count: 0 });
  const loadingMore  = useRef(false);
  const scrollRef    = useRef(null); // ref for the detail modal ScrollView

  const feedRows = useCallback(() => {
    const list = products.slice(1);
    const rows = [];
    let buffer = [];
    let count = 0;

    const flush = () => {
      if (!buffer.length) return;
      rows.push({ type: 'products_row', key: `row_${rows.length}`, items: buffer });
      buffer = [];
    };

    list.forEach((p, idx) => {
      buffer.push(p);
      count += 1;
      if (buffer.length === 2) flush();

      // Insert promo after every 4 products (full-width row)
      if (count % 4 === 0) {
        flush();
        const promo = list[idx] || p || products[0];
        rows.push({ type: 'promo', key: `promo_${rows.length}_${promo?.id ?? idx}`, item: promo });
      }
    });
    flush();

    return rows;
  }, [products]);

  // Bottom bar (hide on scroll down, show on scroll up)
  const bottomBarY = useRef(new Animated.Value(140)).current; // start hidden
  const bottomBarVisibleRef = useRef(false);
  const lastScrollYRef = useRef(0);
  const hideBottomBar = useCallback(() => {
    if (!bottomBarVisibleRef.current) return;
    bottomBarVisibleRef.current = false;
    Animated.timing(bottomBarY, {
      toValue: 120,
      duration: 180,
      useNativeDriver: true,
    }).start();
  }, [bottomBarY]);
  const showBottomBar = useCallback(() => {
    if (bottomBarVisibleRef.current) return;
    bottomBarVisibleRef.current = true;
    Animated.timing(bottomBarY, {
      toValue: 0,
      duration: 180,
      useNativeDriver: true,
    }).start();
  }, [bottomBarY]);

  // Swipe gestures: right → Home, left → My Listings
  const swipe = useRef(
    PanResponder.create({
      onMoveShouldSetPanResponder: (_, g) =>
        !selected && Math.abs(g.dx) > 20 && Math.abs(g.dx) > Math.abs(g.dy) * 1.5,
      onPanResponderRelease: (_, g) => {
        if (g.dx > 60)  navigation.navigate('Main');        // swipe right → Home
        if (g.dx < -60) navigation.navigate('MyProducts');  // swipe left  → My Listings
      },
    })
  ).current;

  const getLocation = async () => {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status === 'granted') {
        const loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
        setLocation({ lat: loc.coords.latitude, lng: loc.coords.longitude });
        return { lat: loc.coords.latitude, lng: loc.coords.longitude };
      }
    } catch {}
    // Default: Lusaka CBD
    return { lat: -15.4166, lng: 28.2833 };
  };

  const fetchProducts = useCallback(async (pageNum = 1, cat = category, loc = location, replace = false) => {
    if (loadingMore.current && !replace) return;
    loadingMore.current = true;
    try {
      const coords = loc || await getLocation();
      const params = {
        lat: coords.lat, lng: coords.lng, radius: 50,
        per_page: 10, page: pageNum,
        ...(cat !== 'All' ? { category: cat } : {}),
      };
      const res = await api.get('/products/nearby', { params });
      const data = res.data.data || res.data;
      if (replace || pageNum === 1) {
        setProducts(data);
      } else {
        setProducts(prev => [...prev, ...data]);
      }
      setError(null);
      setHasMore((res.data.current_page ?? pageNum) < (res.data.last_page ?? 1));
      setPage(pageNum);
    } catch (e) {
      setError(e?.response?.data?.message || e?.message || 'Unable to load nearby products.');
    } finally {
      setLoading(false);
      loadingMore.current = false;
    }
  }, [category, location]);

  const fetchCategories = useCallback(async () => {
    try {
      const res = await api.get('/categories');
      const list = res?.data?.data || res?.data || [];
      const normalized = (Array.isArray(list) ? list : [])
        .filter((c) => c && c.is_active !== false)
        .map((c) => ({ name: String(c.name || '').trim(), slug: String(c.slug || '').trim() }))
        .filter((c) => c.name && c.slug);

      setCategories([{ name: 'All', slug: 'all' }, ...normalized]);
    } catch (_) {
      setCategories(DEFAULT_CATEGORIES);
    }
  }, []);

  useEffect(() => {
    (async () => {
      setLoading(true);
      await fetchCategories();
      const loc = await getLocation();
      setLocation(loc);
      await fetchProducts(1, category, loc, true);
    })();
  }, []);

  const onRefresh = async () => {
    setRefreshing(true);
    await fetchCategories();
    await fetchProducts(1, category, location, true);
    setRefreshing(false);
  };

  const onCategoryPress = async (cat) => {
    setCategory(cat.slug === 'all' ? 'All' : cat.slug);
    setLoading(true);
    await fetchProducts(1, cat.slug === 'all' ? 'All' : cat.slug, location, true);
  };

  const onLoadMore = () => {
    if (hasMore && !loadingMore.current) {
      fetchProducts(page + 1, category, location, false);
    }
  };

  // Record click on the backend (increments clicks counter) then open detail modal
  const handleProductPress = async (item) => {
    setSelected(item);
    setComments([]);
    setCommentText('');
    setLikeState({ liked: false, like_count: 0 });
    // Load click count, comments and like status in parallel
    try {
      const [productRes, commentsRes, likeRes] = await Promise.all([
        api.get(`/products/${item.id}`),
        api.get(`/products/${item.id}/comments`),
        api.get(`/products/${item.id}/like`),
      ]);
      setProducts(prev => prev.map(p => p.id === item.id ? { ...p, clicks: productRes.data.clicks } : p));
      // Reverse so oldest is at top, newest at bottom (chat order)
      const fetched = commentsRes.data.data || commentsRes.data;
      setComments([...fetched].reverse());
      setExpandedGroups({});
      setLikeState({ liked: likeRes.data.liked, like_count: likeRes.data.like_count });
    } catch (_) {}
  };

  const contactSeller = async () => {
    const product = selected;
    const seller = product?.seller;
    if (!product?.id || !seller?.id || contactingSeller) return;

    setContactingSeller(true);
    try {
      const conversationId = await ensureDirectConversation(Number(seller.id));
      if (!conversationId) {
        await alert({ title: 'Could not start chat', message: 'Please try again.', tone: 'danger' });
        return;
      }

      setSelected(null);
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
      setContactingSeller(false);
    }
  };

  const handleToggleLike = async () => {
    if (!selected) return;
    const prev = likeState;
    // Optimistic update
    setLikeState(s => ({ liked: !s.liked, like_count: s.liked ? s.like_count - 1 : s.like_count + 1 }));
    try {
      const res = await api.post(`/products/${selected.id}/like`);
      setLikeState({ liked: res.data.liked, like_count: res.data.like_count });
    } catch {
      setLikeState(prev); // rollback
    }
  };

  const handleSendComment = async () => {
    if (!commentText.trim() || !selected) return;
    const body = commentText.trim();
    setSendingComment(true);
    setCommentText('');
    try {
      const res = await api.post(`/products/${selected.id}/comments`, { body });
      // Append to end so new comment appears at the bottom
      setComments(prev => [...prev, res.data]);
      // Wait for the new item to render, then scroll to the bottom
      setTimeout(() => scrollRef.current?.scrollToEnd({ animated: true }), 80);
    } catch {
      setCommentText(body); // restore on failure
    } finally {
      setSendingComment(false);
    }
  };

  const handleDeleteComment = async (comment) => {
    if (!selected) return;
    setComments(prev => prev.filter(c => c.id !== comment.id));
    try {
      await api.delete(`/products/${selected.id}/comments/${comment.id}`);
    } catch {
      setComments(prev => [...prev, comment]); // rollback — append back at end
    }
  };

  const handleAddToCart = async () => {
    if (!selected) return;
    // Close the product-detail modal first so our dialog can render above it
    // reliably on iOS (where stacking RN Modals can fail).
    const product = selected;
    setSelected(null);

    setAddingToCart(true);
    const res = await addItem(product.id, 1);
    setAddingToCart(false);
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
   * Bottom-sheet entrypoint. Mirrors the `Get it` button on the standalone
   * ProductDetail screen: we first dismiss the product-detail modal to
   * avoid iOS modal-on-modal stacking issues, then show the chooser that
   * routes to either Buy-Now (cart → Checkout) or Buy-for-Me (request flow).
   */
  const openGetItChooser = () => {
    if (!selected) return;
    const product = selected;
    setSelected(null);
    setGetItProduct(product);
    // Give RN a tick to fully unmount the detail modal before we stack the
    // chooser on top — prevents the occasional blank sheet on iOS.
    setTimeout(() => setGetItVisible(true), 150);
  };

  /**
   * Chooser → Buy Now (express checkout).
   * Adds the item to the cart, then jumps to the Checkout screen so the
   * user can review the full order (including anything they already had
   * queued) before paying. 2% cashback rule still applies.
   */
  const chooseBuyNow = async () => {
    const product = getItProduct;
    setGetItVisible(false);
    if (!product) return;

    setBuying(true);
    const res = await addItem(product.id, 1);
    setBuying(false);

    if (!res.ok) {
      await alert({
        title: 'Could not add to cart',
        message: res.message || 'Please try again.',
        tone: 'danger',
      });
      return;
    }
    navigation.navigate('Cart'); // Cart screen has the Checkout CTA; keeps parity with NearbyProducts' existing cart-first flow.
  };

  /**
   * Chooser → Buy for Me (send request).
   * Navigates to the dedicated requester page which handles optional
   * ExtraCash-Number targeting, QR generation, sharing, and cancel.
   */
  const chooseBuyForMe = () => {
    const product = getItProduct;
    setGetItVisible(false);
    if (!product) return;
    navigation.navigate('RequestBuyForMe', {
      productId: product.id,
      product,
    });
  };

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: '#F9FAFB' }} {...swipe.panHandlers}>
      {/* Header */}
      <TouchableOpacity style={styles.header} onPress={() => navigation.navigate('Main')} activeOpacity={0.85}>
        <View style={styles.backBtn}>
          <Feather name="chevron-left" size={22} color="#111827" />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.headerTitle}>Nearby Products</Text>
          <Text style={styles.headerSub}>
            {location ? 'Showing items within 50 km' : 'Locating you…'}
          </Text>
        </View>
        <TouchableOpacity
          onPress={() => navigation.navigate('Cart')}
          style={styles.cartHeaderBtn}
          hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
        >
          <Feather name="shopping-cart" size={16} color="#111827" />
          {cartCount > 0 ? (
            <View style={styles.cartHeaderBadge}>
              <Text style={styles.cartHeaderBadgeText}>{cartCount > 9 ? '9+' : cartCount}</Text>
            </View>
          ) : null}
        </TouchableOpacity>
        <TouchableOpacity onPress={() => navigation.navigate('MyProducts', { openSell: true })} style={styles.sellBtn} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
          <Feather name="plus" size={16} color="#fff" />
          <Text style={styles.sellBtnText}>Sell</Text>
        </TouchableOpacity>
      </TouchableOpacity>

      {/* Category chips */}
      <View>
        <FlatList
          data={categories}
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={{ paddingHorizontal: 14, paddingVertical: 10, gap: 8 }}
          keyExtractor={(c) => c.slug}
          renderItem={({ item: cat }) => (
            <TouchableOpacity
              style={[styles.chip, (cat.slug === 'all' ? 'All' : cat.slug) === category && styles.chipActive]}
              onPress={() => onCategoryPress(cat)}
            >
              <Text style={[styles.chipText, (cat.slug === 'all' ? 'All' : cat.slug) === category && styles.chipTextActive]}>{cat.name}</Text>
            </TouchableOpacity>
          )}
        />
      </View>

      {/* Product grid */}
      {loading ? (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color="#000" />
          <Text style={{ marginTop: 12, color: '#6B7280', fontSize: 13 }}>Finding products near you…</Text>
        </View>
      ) : error ? (
        <View style={styles.empty}>
          <Feather name="alert-circle" size={40} color="#D1D5DB" />
          <Text style={styles.emptyTitle}>Could not load products</Text>
          <Text style={styles.emptySub}>{error}</Text>
          <TouchableOpacity style={styles.listNowBtn} onPress={() => fetchProducts(1, category, location, true)}>
            <Text style={styles.listNowText}>Try again</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={feedRows()}
          keyExtractor={(row) => row.key}
          contentContainerStyle={{ padding: 12, paddingBottom: 12 + Math.max(insets.bottom, 12) + 70 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
          onEndReached={onLoadMore}
          onEndReachedThreshold={0.3}
          onScroll={(e) => {
            const y = e.nativeEvent.contentOffset.y || 0;
            const dy = y - lastScrollYRef.current;
            lastScrollYRef.current = y;

            if (y < 20) {
              hideBottomBar();
              return;
            }
            if (dy > 8) hideBottomBar();       // scrolling down → hide
            else if (dy < -8) showBottomBar(); // scrolling up → reveal
          }}
          scrollEventThrottle={16}
          ListFooterComponent={hasMore ? <ActivityIndicator color="#000" style={{ padding: 16 }} /> : null}
          ListHeaderComponent={
            products.length ? (
              <View style={{ marginBottom: 10 }}>
                <ProductCard item={products[0]} onPress={handleProductPress} featured />
              </View>
            ) : null
          }
          ListEmptyComponent={
            <View style={styles.empty}>
              <Feather name="map-pin" size={40} color="#D1D5DB" />
              <Text style={styles.emptyTitle}>No products nearby</Text>
              <Text style={styles.emptySub}>Be the first to list something in your area!</Text>
              <TouchableOpacity style={styles.listNowBtn} onPress={() => navigation.navigate('MyProducts', { openSell: true })}>
                <Text style={styles.listNowText}>List a Product</Text>
              </TouchableOpacity>
            </View>
          }
          renderItem={({ item: row }) => {
            if (row.type === 'promo') {
              return (
                <View style={{ marginBottom: 10 }}>
                  <PromoCard item={row.item} onPress={handleProductPress} />
                </View>
              );
            }

            const left = row.items?.[0] || null;
            const right = row.items?.[1] || null;
            return (
              <View style={styles.gridRow}>
                <View style={styles.gridCol}>
                  {left ? <ProductCard item={left} onPress={handleProductPress} /> : null}
                </View>
                <View style={styles.gridCol}>
                  {right ? <ProductCard item={right} onPress={handleProductPress} /> : null}
                </View>
              </View>
            );
          }}
        />
      )}

      <Animated.View style={[styles.bottomTabsWrap, { transform: [{ translateY: bottomBarY }] }]}>
        <BottomTabsOverlay navigation={navigation} active="Home" />
      </Animated.View>

      {/* Product Detail Modal */}
      <Modal visible={!!selected} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setSelected(null)}>
        {selected && (
          <SafeAreaView style={{ flex: 1, backgroundColor: '#fff' }}>
            <KeyboardAvoidingView
              behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
              style={{ flex: 1 }}
              keyboardVerticalOffset={0}
            >
              {/* ── Header ── */}
              <View style={styles.modalHeader}>
                <TouchableOpacity onPress={() => setSelected(null)}>
                  <Feather name="x" size={22} color="#111" />
                </TouchableOpacity>
                <Text style={styles.modalTitle} numberOfLines={1}>{selected.title}</Text>
                <View style={{ width: 22 }} />
              </View>

              {/* ── Scrollable content ── */}
              <ScrollView
                ref={scrollRef}
                contentContainerStyle={{ padding: 20, paddingBottom: 8 }}
                keyboardShouldPersistTaps="handled"
                style={{ flex: 1 }}
              >
                {/* Hero image */}
                <View style={styles.modalImage}>
                  {selected.image_url
                    ? <Image source={{ uri: selected.image_url }} style={{ width: '100%', height: '100%', borderRadius: 12 }} resizeMode="cover" />
                    : <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
                        <Feather name="package" size={64} color="#E5E7EB" />
                      </View>
                  }
                </View>

                {/* Price + Cashback */}
                <View style={styles.detailPriceRow}>
                  <View>
                    <Text style={styles.detailPriceLabel}>Price</Text>
                    <Text style={styles.detailPrice}>ZMW {parseFloat(selected.price).toFixed(2)}</Text>
                  </View>
                  <View style={styles.detailCashbackBox}>
                    <Feather name="gift" size={14} color="#fff" />
                    <View>
                      <Text style={styles.detailCashbackLabel}>You earn</Text>
                      <Text style={styles.detailCashbackAmount}>ZMW {parseFloat(selected.cashback_amount || 0).toFixed(2)}</Text>
                    </View>
                  </View>
                </View>

                <Text style={styles.detailCashbackNote}>
                  {((selected.cashback_rate || 0.02) * 100).toFixed(0)}% cashback credited to your ExtraCash wallet after purchase
                </Text>

                {/* Meta */}
                <View style={styles.detailMeta}>
                  <View style={styles.detailMetaItem}>
                    <Feather name="tag" size={13} color="#6B7280" />
                    <Text style={styles.detailMetaText}>{selected.category || 'General'}</Text>
                  </View>
                  <View style={styles.detailMetaItem}>
                    <Feather name="star" size={13} color="#6B7280" />
                    <Text style={styles.detailMetaText}>{selected.condition}</Text>
                  </View>
                  <View style={styles.detailMetaItem}>
                    <Feather name="box" size={13} color="#6B7280" />
                    <Text style={styles.detailMetaText}>{selected.stock} in stock</Text>
                  </View>
                  <View style={styles.detailMetaItem}>
                    <Feather name="map-pin" size={13} color="#6B7280" />
                    <Text style={styles.detailMetaText}>{selected.location_label || 'Nearby'}</Text>
                  </View>
                </View>

                {selected.description ? (
                  <>
                    <Text style={styles.detailSection}>Description</Text>
                    <Text style={styles.detailDesc}>{selected.description}</Text>
                  </>
                ) : null}

                {/* Seller + Like */}
                <Text style={styles.detailSection}>Seller</Text>
                <View style={styles.sellerRow}>
                  <View style={styles.sellerAvatar}>
                    <Text style={styles.sellerAvatarText}>
                      {(selected.seller?.name || 'S').slice(0, 2).toUpperCase()}
                    </Text>
                  </View>
                  <Text style={styles.sellerName}>{selected.seller?.name || 'Seller'}</Text>
                  <TouchableOpacity style={styles.likeBtn} onPress={handleToggleLike} activeOpacity={0.8}>
                    <Feather name="heart" size={16} color={likeState.liked ? '#EF4444' : '#9CA3AF'} />
                    <Text style={[styles.likeBtnText, likeState.liked && { color: '#EF4444' }]}>
                      {likeState.like_count > 0 ? `${likeState.like_count} ` : ''}{likeState.liked ? 'Loved' : 'Love this'}
                    </Text>
                  </TouchableOpacity>
                </View>

                {/* ── Comments list ── */}
                <View style={styles.forumHeader}>
                  <Feather name="message-circle" size={15} color="#374151" />
                  <Text style={styles.forumTitle}>
                    Community · {comments.length} {comments.length === 1 ? 'comment' : 'comments'}
                  </Text>
                </View>

                {comments.length === 0 ? (
                  <View style={styles.noComments}>
                    <Feather name="message-square" size={24} color="#E5E7EB" />
                    <Text style={styles.noCommentsText}>Be the first to comment!</Text>
                  </View>
                ) : (
                  (() => {
                    // Group consecutive comments from the same user
                    const groups = [];
                    comments.forEach(c => {
                      const last = groups[groups.length - 1];
                      if (last && last[0].user?.id === c.user?.id) {
                        last.push(c);
                      } else {
                        groups.push([c]);
                      }
                    });

                    return groups.map((group, gi) => {
                      const first = group[0];
                      const extra = group.slice(1);
                      const isExpanded = !!expandedGroups[gi];

                      return (
                        <TouchableOpacity
                          key={`group-${gi}`}
                          style={styles.commentCard}
                          onPress={() => extra.length > 0 && setExpandedGroups(prev => ({ ...prev, [gi]: !prev[gi] }))}
                          activeOpacity={extra.length > 0 ? 0.7 : 1}
                        >
                          <View style={styles.commentAvatar}>
                            <Text style={styles.commentAvatarText}>
                              {(first.user?.name || 'U').slice(0, 2).toUpperCase()}
                            </Text>
                          </View>
                          <View style={{ flex: 1 }}>
                            {/* Header row: name + time + delete */}
                            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                                <Text style={styles.commentAuthor}>{first.user?.name || 'User'}</Text>
                                <Text style={styles.commentTime}>
                                  {new Date(first.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                </Text>
                              </View>
                              <TouchableOpacity onPress={() => handleDeleteComment(first)} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                                <Feather name="x" size={12} color="#D1D5DB" />
                              </TouchableOpacity>
                            </View>

                            {/* First (always visible) comment body */}
                            <Text style={styles.commentBody}>{first.body}</Text>

                            {/* Extra comments — shown when expanded */}
                            {isExpanded && extra.map(c => (
                              <View key={c.id} style={{ marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderTopColor: '#F3F4F6' }}>
                                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                                  <Text style={styles.commentTime}>
                                    {new Date(c.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                  </Text>
                                  <TouchableOpacity onPress={() => handleDeleteComment(c)} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                                    <Feather name="x" size={12} color="#D1D5DB" />
                                  </TouchableOpacity>
                                </View>
                                <Text style={styles.commentBody}>{c.body}</Text>
                              </View>
                            ))}

                            {/* Expand / collapse pill */}
                            {extra.length > 0 && (
                              <TouchableOpacity
                                onPress={() => setExpandedGroups(prev => ({ ...prev, [gi]: !prev[gi] }))}
                                style={{ marginTop: 6, alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 4 }}
                                hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                              >
                                <Feather name={isExpanded ? 'chevron-up' : 'chevron-down'} size={12} color="#6B7280" />
                                <Text style={{ fontSize: 11, color: '#6B7280' }}>
                                  {isExpanded ? 'Show less' : `+${extra.length} more`}
                                </Text>
                              </TouchableOpacity>
                            )}
                          </View>
                        </TouchableOpacity>
                      );
                    });
                  })()
                )}
              </ScrollView>

              {/* ── Sticky comment input bar (always visible above keyboard) ── */}
              <View style={styles.commentBar}>
                <TextInput
                  style={styles.commentBarInput}
                  value={commentText}
                  onChangeText={setCommentText}
                  placeholder="Add a comment…"
                  placeholderTextColor="#9CA3AF"
                  multiline
                  maxLength={500}
                  returnKeyType="default"
                  onFocus={() => setTimeout(() => scrollRef.current?.scrollToEnd({ animated: true }), 150)}
                />
                <TouchableOpacity
                  style={[styles.commentSendBtn, (!commentText.trim() || sendingComment) && { opacity: 0.35 }]}
                  onPress={handleSendComment}
                  disabled={!commentText.trim() || sendingComment}
                >
                  {sendingComment
                    ? <ActivityIndicator size="small" color="#fff" />
                    : <Feather name="send" size={15} color="#fff" />
                  }
                </TouchableOpacity>
              </View>

              {/* ── Buy / Add-to-Cart footer ── */}
              <View style={styles.modalFooter}>
                <TouchableOpacity
                  style={[styles.addCartBtn, (addingToCart || cartMutating) && styles.addCartBtnDisabled]}
                  onPress={handleAddToCart}
                  disabled={addingToCart || cartMutating}
                >
                  {addingToCart
                    ? <ActivityIndicator size="small" color="#111" />
                    : <Feather name="shopping-cart" size={18} color="#000" />
                  }
                </TouchableOpacity>

                <TouchableOpacity
                  style={[
                    styles.contactBtn,
                    (!selected?.seller?.id || contactingSeller) && styles.contactBtnDisabled,
                  ]}
                  onPress={contactSeller}
                  disabled={!selected?.seller?.id || contactingSeller}
                  activeOpacity={0.85}
                >
                  {contactingSeller
                    ? <ActivityIndicator size="small" color="#111" />
                    : <Feather name="message-circle" size={16} color="#111827" />
                  }
                  <Text style={styles.contactBtnText}>Contact seller</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={[styles.buyBtn, buying && styles.buyBtnDisabled]}
                  onPress={openGetItChooser}
                  disabled={buying}
                >
                  {buying
                    ? <ActivityIndicator size="small" color="#fff" />
                    : <Feather name="zap" size={16} color="#fff" />
                  }
                  <Text style={styles.buyBtnText}>{buying ? 'Processing…' : 'Get it'}</Text>
                </TouchableOpacity>
              </View>
            </KeyboardAvoidingView>
          </SafeAreaView>
        )}
      </Modal>

      {/* ── "Get it" chooser ─────────────────────────────────────
          Bottom sheet that mirrors the ProductDetail screen. Shown
          after the detail modal has dismissed so it renders cleanly
          on iOS. Routes to Buy-Now (cart) or Buy-for-Me (request). */}
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
            {getItProduct ? (
              <Text style={styles.giSubtitle} numberOfLines={2}>
                "{getItProduct.title}" · ZMW {parseFloat(getItProduct.price).toFixed(2)}
              </Text>
            ) : null}

            {/* Buy Now — express checkout */}
            <TouchableOpacity
              style={styles.giOption}
              onPress={chooseBuyNow}
              activeOpacity={0.85}
              disabled={buying}
            >
              <View style={[styles.giOptionIcon, { backgroundColor: '#111827' }]}>
                <Feather name="zap" size={18} color="#fff" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.giOptionTitle}>Buy Now</Text>
                <Text style={styles.giOptionSub}>
                  Pay from your wallet and earn ZMW{' '}
                  {parseFloat(getItProduct?.cashback_amount || 0).toFixed(2)} cashback.
                </Text>
              </View>
              <Feather name="chevron-right" size={18} color="#9CA3AF" />
            </TouchableOpacity>

            {/* Buy for Me — send request */}
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
  gridRow: { flexDirection: 'row', gap: 10, marginBottom: 10 },
  gridCol: { flex: 1 },

  promoCard: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#fff',
    overflow: 'hidden',
  },
  promoImage: {
    height: 180,
    backgroundColor: '#F3F4F6',
    borderRadius: 16,
    overflow: 'hidden',
    margin: 10,
  },
  promoBadgeLeft: {
    position: 'absolute',
    top: 12,
    left: 12,
    backgroundColor: 'rgba(17,24,39,0.9)',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 10,
  },
  promoBadgeText: { color: '#fff', fontSize: 11, fontWeight: '800', letterSpacing: 0.6 },
  promoBadgeRight: {
    position: 'absolute',
    top: 12,
    right: 12,
    backgroundColor: 'rgba(255,255,255,0.92)',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: 'rgba(17,24,39,0.08)',
  },
  promoBadgeRightText: { color: '#111827', fontSize: 10, fontWeight: '900', letterSpacing: 0.6 },
  promoBody: { paddingHorizontal: 14, paddingBottom: 14 },
  promoCategory: { color: '#9CA3AF', fontSize: 12, fontWeight: '800', letterSpacing: 1 },
  promoTitle: { marginTop: 6, fontSize: 18, fontWeight: '900', color: '#111827', letterSpacing: -0.2 },
  promoPriceRow: { marginTop: 10, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 },
  promoPrice: { fontSize: 20, fontWeight: '900', color: '#111827' },
  header: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 14, paddingVertical: 12, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  backBtn: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  headerTitle: { fontSize: 15, fontWeight: '800', color: '#111827' },
  headerSub: { fontSize: 11, color: '#9CA3AF', marginTop: 1 },
  cartHeaderBtn: {
    width: 36, height: 36, borderRadius: 18,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
    position: 'relative',
  },
  cartHeaderBadge: {
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
  cartHeaderBadgeText: { color: '#fff', fontSize: 9, fontWeight: '800' },
  sellBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: '#000', paddingHorizontal: 12, paddingVertical: 7, borderRadius: 8 },
  sellBtnText: { color: '#fff', fontWeight: '700', fontSize: 13 },

  chip: { paddingHorizontal: 14, paddingVertical: 6, borderRadius: 20, backgroundColor: '#F3F4F6', borderWidth: 1, borderColor: '#E5E7EB' },
  chipActive: { backgroundColor: '#000', borderColor: '#000' },
  chipText: { fontSize: 12, fontWeight: '600', color: '#6B7280' },
  chipTextActive: { color: '#fff' },

  bottomTabsWrap: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
  },

  card: { flex: 1, backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB', overflow: 'hidden' },
  cardFeatured: { flex: undefined, width: '100%', flexDirection: 'row', height: 160, overflow: 'hidden' },
  cardImage: { height: 140, backgroundColor: '#F3F4F6', position: 'relative', overflow: 'hidden' },
  cardImageFeatured: { width: 155, height: 160, flex: 0 },
  cardImagePlaceholder: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  conditionBadge: { position: 'absolute', top: 8, left: 8, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4 },
  conditionText: { color: '#fff', fontSize: 9, fontWeight: '700', textTransform: 'uppercase' },
  clicksBadge: { position: 'absolute', top: 8, right: 8, flexDirection: 'row', alignItems: 'center', gap: 3, backgroundColor: 'rgba(0,0,0,0.55)', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 10 },
  clicksBadgeText: { fontSize: 9, color: '#fff', fontWeight: '600' },
  featuredBadge: { position: 'absolute', bottom: 8, left: 8, flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#000', paddingHorizontal: 7, paddingVertical: 3, borderRadius: 10 },
  featuredBadgeText: { fontSize: 9, color: '#fff', fontWeight: '700' },
  cardBody: { padding: 10, flex: 1 },
  cardBodyFeatured: { justifyContent: 'center', overflow: 'hidden' },
  cardCategory: { fontSize: 10, fontWeight: '700', color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 3 },
  cardTitle: { fontSize: 13, fontWeight: '700', color: '#111827', lineHeight: 18 },
  cardTitleFeatured: { fontSize: 15, lineHeight: 20 },
  cardMeta: { flexDirection: 'row', alignItems: 'center', gap: 3, marginTop: 4 },
  cardLocation: { fontSize: 10, color: '#9CA3AF', flex: 1 },
  clicksRow: { flexDirection: 'row', alignItems: 'center', gap: 3, marginTop: 2 },
  clicksText: { fontSize: 10, color: '#6B7280' },
  priceRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 8, flexWrap: 'wrap', gap: 4 },
  price: { fontSize: 14, fontWeight: '800', color: '#111827' },
  priceFeatured: { fontSize: 16 },
  cashbackPill: { flexDirection: 'row', alignItems: 'center', gap: 3, backgroundColor: '#000', paddingHorizontal: 6, paddingVertical: 3, borderRadius: 6 },
  cashbackText: { color: '#fff', fontSize: 9, fontWeight: '700' },
  cashbackSub: { fontSize: 10, color: '#6B7280', marginTop: 3 },

  empty: { padding: 50, alignItems: 'center', gap: 8 },
  emptyTitle: { fontWeight: '800', fontSize: 16, color: '#374151' },
  emptySub: { color: '#9CA3AF', fontSize: 13, textAlign: 'center' },
  listNowBtn: { marginTop: 8, backgroundColor: '#000', paddingHorizontal: 20, paddingVertical: 10, borderRadius: 10 },
  listNowText: { color: '#fff', fontWeight: '700', fontSize: 13 },

  // Modal
  modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', padding: 16, borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  modalTitle: { flex: 1, fontSize: 15, fontWeight: '800', color: '#111', textAlign: 'center', marginHorizontal: 8 },
  modalImage: { height: 220, backgroundColor: '#F3F4F6', borderRadius: 12, marginBottom: 16, overflow: 'hidden' },
  detailPriceRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  detailPriceLabel: { fontSize: 11, color: '#9CA3AF', fontWeight: '600', textTransform: 'uppercase' },
  detailPrice: { fontSize: 24, fontWeight: '900', color: '#111' },
  detailCashbackBox: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#000', padding: 12, borderRadius: 10 },
  detailCashbackLabel: { fontSize: 10, color: '#D1D5DB', fontWeight: '600' },
  detailCashbackAmount: { fontSize: 16, fontWeight: '900', color: '#fff' },
  detailCashbackNote: { fontSize: 11, color: '#6B7280', marginBottom: 16 },
  detailMeta: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, backgroundColor: '#F9FAFB', borderRadius: 10, padding: 12, marginBottom: 16 },
  detailMetaItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  detailMetaText: { fontSize: 12, color: '#374151', fontWeight: '600' },
  detailSection: { fontSize: 12, fontWeight: '800', color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 6 },
  detailDesc: { fontSize: 14, color: '#374151', lineHeight: 22, marginBottom: 16 },
  sellerRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 20 },
  sellerAvatar: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#000', justifyContent: 'center', alignItems: 'center' },
  sellerAvatarText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  sellerName: { fontSize: 14, fontWeight: '600', color: '#111', flex: 1 },

  // Like button
  likeBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 12, paddingVertical: 7, borderRadius: 20, borderWidth: 1, borderColor: '#E5E7EB', backgroundColor: '#F9FAFB' },
  likeBtnText: { fontSize: 12, fontWeight: '600', color: '#6B7280' },

  // Comments / Forum
  forumSection: { marginBottom: 20 },
  forumHeader: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 12 },
  forumTitle: { fontSize: 14, fontWeight: '800', color: '#111827' },
  // Sticky comment input bar
  commentBar: { flexDirection: 'row', alignItems: 'flex-end', gap: 8, paddingHorizontal: 14, paddingVertical: 10, borderTopWidth: 1, borderTopColor: '#F3F4F6', backgroundColor: '#fff' },
  commentBarInput: { flex: 1, backgroundColor: '#F9FAFB', borderRadius: 20, borderWidth: 1, borderColor: '#E5E7EB', paddingHorizontal: 14, paddingVertical: 9, fontSize: 14, color: '#111827', maxHeight: 100, minHeight: 40 },
  commentSendBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#000', justifyContent: 'center', alignItems: 'center', flexShrink: 0 },
  noComments: { alignItems: 'center', paddingVertical: 24, gap: 6 },
  noCommentsText: { fontSize: 13, color: '#9CA3AF' },
  commentCard: { flexDirection: 'row', gap: 10, marginBottom: 12, backgroundColor: '#F9FAFB', borderRadius: 12, padding: 12, borderWidth: 1, borderColor: '#F3F4F6' },
  commentAvatar: { width: 32, height: 32, borderRadius: 16, backgroundColor: '#111827', justifyContent: 'center', alignItems: 'center', flexShrink: 0 },
  commentAvatarText: { color: '#fff', fontWeight: '700', fontSize: 11 },
  commentAuthor: { fontSize: 12, fontWeight: '700', color: '#111827' },
  commentBody: { fontSize: 13, color: '#374151', lineHeight: 19, marginTop: 3 },
  commentTime: { fontSize: 10, color: '#9CA3AF' },

  modalFooter: {
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

  /* ── "Get it" chooser bottom sheet ───────────────────────── */
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
  giTitle:    { fontSize: 18, fontWeight: '900', color: '#111827' },
  giSubtitle: { fontSize: 12, color: '#6B7280', marginBottom: 8 },
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
  giOptionTitle: { fontSize: 14, fontWeight: '800', color: '#111827' },
  giOptionSub:   { fontSize: 12, color: '#6B7280', marginTop: 2 },
  giCancel:      { marginTop: 6, alignItems: 'center', paddingVertical: 12 },
  giCancelText:  { fontSize: 14, fontWeight: '700', color: '#6B7280' },
});
