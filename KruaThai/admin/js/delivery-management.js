/**
 * Somdul Table - Delivery Management JavaScript
 */

// Global variables
let map;
let markers = [];
const deliveryData = window.deliveryData;

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
    updateMapMarkers();
    console.log('🚚 Somdul Table Delivery Management initialized');
    console.log(`📊 Managing ${deliveryData.orders.length} orders for ${deliveryData.date}`);
});

// ======================================================================
// MAP FUNCTIONS
// ======================================================================

function initializeMap() {
    // Initialize the map
    map = L.map('map').setView([deliveryData.shopLocation.lat, deliveryData.shopLocation.lng], 11);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add restaurant marker
    const shopIcon = L.divIcon({
        className: 'shop-marker',
        html: `<div style="background: #cf723a; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); font-size: 18px;"><i class="fas fa-store"></i></div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });
    
    L.marker([deliveryData.shopLocation.lat, deliveryData.shopLocation.lng], {icon: shopIcon})
        .addTo(map)
        .bindPopup(`<strong>${deliveryData.shopLocation.name}</strong><br>${deliveryData.shopLocation.address}`)
        .openPopup();
}

function updateMapMarkers() {
    // Clear existing customer markers
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];
    
    // Add customer markers
    deliveryData.orders.forEach((order, index) => {
        if (order.latitude && order.longitude) {
            const sequence = order.delivery_sequence || (index + 1);
            
            const customerIcon = L.divIcon({
                className: 'customer-marker',
                html: `<div style="background: #bd9379; color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); font-size: 14px; font-weight: bold;">${sequence}</div>`,
                iconSize: [35, 35],
                iconAnchor: [17, 17]
            });
            
            const riderInfo = order.rider_first_name ? 
                `🚚 ${order.rider_first_name} ${order.rider_last_name}` : 
                '⚠️ Unassigned';
            
            const marker = L.marker([order.latitude, order.longitude], {icon: customerIcon})
                .addTo(map)
                .bindPopup(`
                    <div style="min-width: 200px;">
                        <strong>#${sequence} - ${order.order_number}</strong><br>
                        <strong>${order.first_name} ${order.last_name}</strong><br>
                        📞 ${order.phone}<br>
                        📦 ${order.total_items} meals<br>
                        📍 ${order.distance ? order.distance + ' miles' : 'Distance unknown'}<br>
                        ${riderInfo}
                    </div>
                `);
            
            markers.push(marker);
        }
    });
}

// ======================================================================
// ORDER MANAGEMENT FUNCTIONS
// ======================================================================

function generateOrders() {
    showLoading();
    
    fetch('delivery-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=auto_generate_orders&date=${deliveryData.date}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Orders Generated!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showError('Failed to generate orders');
    });
}

function optimizeDeliveryOrder() {
    if (deliveryData.orders.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Orders',
            text: 'No orders available to optimize'
        });
        return;
    }
    
    Swal.fire({
        title: 'Optimize Delivery Order?',
        text: 'This will reorganize the delivery sequence for maximum efficiency.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Optimize Now',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('delivery-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=optimize_delivery_order&date=${deliveryData.date}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    // Show success message with optimization results
                    Swal.fire({
                        icon: 'success',
                        title: 'Delivery Order Optimized!',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>${data.message}</strong></p>
                                <hr style="margin: 1rem 0;">
                                <p><strong>Optimization Results:</strong></p>
                                <ul style="margin: 0.5rem 0;">
                                    <li>Total Distance: ${data.total_distance.toFixed(1)} miles</li>
                                    <li>Orders Optimized: ${data.optimized_orders.length}</li>
                                    <li>Route Efficiency: Improved</li>
                                </ul>
                            </div>
                        `,
                        timer: 4000
                    }).then(() => {
                        // Animate the reordered items
                        animateOptimizedOrder(data.optimized_orders);
                        // Reload page after animation
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Optimization Failed',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showError('Failed to optimize delivery order');
            });
        }
    });
}

function animateOptimizedOrder(optimizedOrders) {
    // Add animation class to all customer items
    const customerItems = document.querySelectorAll('.customer-item');
    customerItems.forEach((item, index) => {
        setTimeout(() => {
            item.classList.add('optimized');
        }, index * 100);
    });
}

function assignRider(selectElement, subscriptionId) {
    const riderId = selectElement.value;
    
    if (!riderId) {
        return;
    }
    
    showLoading();
    
    // Fixed: Changed order_id to subscription_id to match PHP expectations
    fetch('delivery-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=assign_rider_to_customer&subscription_id=${subscriptionId}&rider_id=${riderId}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            // Find the rider name
            const rider = deliveryData.riders.find(r => r.id === riderId);
            const riderName = rider ? `${rider.first_name} ${rider.last_name}` : 'Unknown';
            
            // Update the UI
            const customerItem = selectElement.closest('.customer-item');
            const actionsDiv = customerItem.querySelector('.customer-actions');
            
            actionsDiv.innerHTML = `
                <div class="assigned-rider">
                    <div class="rider-name">
                        <i class="fas fa-user-check"></i>
                        ${riderName}
                    </div>
                    <button class="remove-rider-btn" onclick="removeRider('${subscriptionId}')" title="Remove rider assignment">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Show success toast
            showToast('success', data.message);
            
            // Update the map marker popup if needed
            updateMapMarkers();
            
            // Refresh the page to update rider routes section
            setTimeout(() => {
                location.reload();
            }, 1500);
            
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Assignment Failed',
                text: data.message
            });
            // Reset the select value
            selectElement.value = '';
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showError('Failed to assign rider. Please try again.');
        // Reset the select value
        selectElement.value = '';
    });
}

// ======================================================================
// UI HELPER FUNCTIONS
// ======================================================================

function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message
    });
}

function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        timer: 2000,
        showConfirmButton: false
    });
}

function showToast(type, message) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    Toast.fire({
        icon: type,
        title: message
    });
}

// ======================================================================
// KEYBOARD SHORTCUTS
// ======================================================================

document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'r':
                e.preventDefault();
                location.reload();
                break;
            case 'o':
                e.preventDefault();
                optimizeDeliveryOrder();
                break;
            case 'g':
                e.preventDefault();
                generateOrders();
                break;
        }
    }
    
    if (e.key === 'Escape') {
        hideLoading();
    }
});

// ======================================================================
// UTILITY FUNCTIONS
// ======================================================================

function refreshPage() {
    location.reload();
}

function formatDistance(distance) {
    if (!distance || distance === 0) {
        return 'Unknown';
    }
    return distance.toFixed(1) + ' miles';
}

function formatPhone(phone) {
    if (!phone) return 'No phone';
    // Format phone number (assuming US format)
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 10) {
        return `(${cleaned.slice(0,3)}) ${cleaned.slice(3,6)}-${cleaned.slice(6)}`;
    }
    return phone;
}

// ======================================================================
// RESPONSIVE FUNCTIONS
// ======================================================================

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('open');
}

// Add mobile menu toggle if needed
if (window.innerWidth <= 768) {
    const header = document.querySelector('.page-header .header-content');
    const menuButton = document.createElement('button');
    menuButton.innerHTML = '<i class="fas fa-bars"></i>';
    menuButton.className = 'btn btn-secondary mobile-menu-btn';
    menuButton.onclick = toggleSidebar;
    header.insertBefore(menuButton, header.firstChild);
}

// ======================================================================
// AUTO-REFRESH FUNCTIONALITY
// ======================================================================

let autoRefreshInterval;

function startAutoRefresh() {
    // Refresh every 30 seconds
    autoRefreshInterval = setInterval(function() {
        if (!document.hidden) {
            silentRefresh();
        }
    }, 30000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

function silentRefresh() {
    // Silent refresh without full page reload
    fetch(`delivery-management.php?date=${deliveryData.date}`)
        .then(response => response.text())
        .then(html => {
            console.log('🔄 Data refreshed silently');
            // Could update specific sections here if needed
        })
        .catch(error => {
            console.error('Silent refresh failed:', error);
        });
}

// Start auto-refresh
startAutoRefresh();

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

// ======================================================================
// ERROR HANDLING
// ======================================================================

window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    // Could send error to logging service here
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled Promise Rejection:', e.reason);
    // Could send error to logging service here
});

// ======================================================================
// SEARCH AND FILTER FUNCTIONALITY
// ======================================================================

function searchCustomers(query) {
    const customerItems = document.querySelectorAll('.customer-item');
    const searchTerm = query.toLowerCase();
    
    customerItems.forEach(item => {
        const customerInfo = item.querySelector('.customer-info');
        const text = customerInfo.textContent.toLowerCase();
        
        if (text.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function filterByRiderStatus(status) {
    const customerItems = document.querySelectorAll('.customer-item');
    
    customerItems.forEach(item => {
        const hasRider = item.querySelector('.assigned-rider') !== null;
        
        if (status === 'all') {
            item.style.display = 'flex';
        } else if (status === 'assigned' && hasRider) {
            item.style.display = 'flex';
        } else if (status === 'unassigned' && !hasRider) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// ======================================================================
// PERFORMANCE OPTIMIZATIONS
// ======================================================================

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Lazy loading for map markers
function lazyLoadMarkers() {
    // Only load markers that are in the current map view
    const bounds = map.getBounds();
    // Implementation would go here
}

// ======================================================================
// ACCESSIBILITY IMPROVEMENTS
// ======================================================================

// Add keyboard navigation for customer list
document.addEventListener('keydown', function(e) {
    const focusedElement = document.activeElement;
    
    if (e.key === 'ArrowDown' && focusedElement.classList.contains('rider-select')) {
        // Handle arrow navigation
        e.preventDefault();
        const customerItems = Array.from(document.querySelectorAll('.customer-item'));
        const currentIndex = customerItems.findIndex(item => item.contains(focusedElement));
        const nextItem = customerItems[currentIndex + 1];
        if (nextItem) {
            const nextSelect = nextItem.querySelector('.rider-select');
            if (nextSelect) nextSelect.focus();
        }
    }
    
    if (e.key === 'ArrowUp' && focusedElement.classList.contains('rider-select')) {
        e.preventDefault();
        const customerItems = Array.from(document.querySelectorAll('.customer-item'));
        const currentIndex = customerItems.findIndex(item => item.contains(focusedElement));
        const prevItem = customerItems[currentIndex - 1];
        if (prevItem) {
            const prevSelect = prevItem.querySelector('.rider-select');
            if (prevSelect) prevSelect.focus();
        }
    }
});

// ======================================================================
// CLEANUP
// ======================================================================

window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
    // Clean up any other resources
});

// Log initialization completion
console.log('🚚 Somdul Table Delivery Management JavaScript loaded successfully');
console.log('⌨️ Keyboard shortcuts: Ctrl+R (Refresh), Ctrl+O (Optimize), Ctrl+G (Generate)');
console.log('📊 Features: Auto-refresh, Map markers, Rider assignment, Order optimization');