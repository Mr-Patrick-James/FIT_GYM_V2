// Walk-in Bookings Management System
class WalkinBookingsManager {
    constructor() {
        this.bookings = [];
        this.filteredBookings = [];
        this.currentSort = 'date-desc';
        this.currentFilters = {
            status: 'all',
            search: '',
            dateFrom: '',
            dateTo: ''
        };
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadPackages();
        this.loadBookings();
        this.setDefaultDate();
        this.loadPaymentSettings();
    }
    
    async loadPaymentSettings() {
        try {
            const response = await fetch('../api/settings/get.php');
            const result = await response.json();
            
            if (result.success) {
                this.paymentSettings = {};
                result.data.forEach(item => {
                    this.paymentSettings[item.setting_key] = item.setting_value;
                });
                
                // Update GCash display info in modal
                const displayNum = document.getElementById('displayGcashNumber');
                const displayName = document.getElementById('displayGcashName');
                const qrContainer = document.getElementById('gcashQRContainer');
                
                if (displayNum) displayNum.textContent = this.paymentSettings.gcash_number || '0917-123-4567';
                if (displayName) displayName.textContent = this.paymentSettings.gcash_name || 'Martinez Fitness';
                
                if (qrContainer) {
                    let qrPath = this.paymentSettings.gcash_qr_path;
                    // Maximize QR size for better visibility in the modal
                    const imgStyle = "width: 100%; height: 100%; object-fit: contain; background: white; padding: 3px; border-radius: 2px;";
                    if (qrPath && qrPath !== '') {
                        if (!qrPath.startsWith('http')) {
                            qrPath = '../../' + qrPath;
                        }
                        qrContainer.innerHTML = `<img src="${qrPath}" style="${imgStyle}">`;
                    } else {
                        const fallbackQR = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=GCash:' + (this.paymentSettings.gcash_number || '09171234567');
                        qrContainer.innerHTML = `<img src="${fallbackQR}" style="${imgStyle}">`;
                    }
                }
            }
        } catch (error) {
            console.error('Error loading payment settings:', error);
        }
    }

    handlePaymentMethodChange(method) {
        const gcashDetails = document.getElementById('gcashDetails');
        if (gcashDetails) {
            gcashDetails.style.display = (method === 'gcash') ? 'block' : 'none';
        }
    }
    
    bindEvents() {
        // Modal controls
        document.getElementById('addWalkinBtn').addEventListener('click', () => this.openWalkinModal());
        document.getElementById('closeWalkinModal').addEventListener('click', () => this.closeWalkinModal());
        document.getElementById('cancelWalkin').addEventListener('click', () => this.closeWalkinModal());
        
        // Form submission
        document.getElementById('walkinForm').addEventListener('submit', (e) => this.handleWalkinSubmit(e));
        
        // Status modal
        document.getElementById('closeStatusModal').addEventListener('click', () => this.closeStatusModal());
        document.getElementById('cancelStatus').addEventListener('click', () => this.closeStatusModal());
        document.getElementById('statusForm').addEventListener('submit', (e) => this.handleStatusSubmit(e));
        
        // Filters
        document.getElementById('statusFilter').addEventListener('change', () => this.applyFilters());
        document.getElementById('searchFilter').addEventListener('input', () => this.applyFilters());
        document.getElementById('dateFrom').addEventListener('change', () => this.applyFilters());
        document.getElementById('dateTo').addEventListener('change', () => this.applyFilters());
        document.getElementById('resetFilters').addEventListener('click', () => this.resetFilters());
        
        // Table sorting
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.addEventListener('click', () => this.handleSort(th.dataset.sort));
        });
        
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        
        // Package selection
        document.getElementById('packageSelect').addEventListener('change', () => this.updatePackageInfo());
        
        // Payment method change
        document.getElementById('paymentMethod').addEventListener('change', (e) => this.handlePaymentMethodChange(e.target.value));
        
        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.closeAllModals();
                }
            });
        });
    }
    
    setDefaultDate() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('bookingDate').value = today;
    }
    
    async loadPackages() {
        try {
            const response = await fetch('../api/packages/get-all.php');
            const result = await response.json();
            
            if (result.success) {
                const packageSelect = document.getElementById('packageSelect');
                packageSelect.innerHTML = '<option value="">Select Package</option>';
                
                result.data.forEach(pkg => {
                    const option = document.createElement('option');
                    option.value = pkg.name;
                    option.textContent = `${pkg.name} - ₱${parseFloat(pkg.price).toFixed(2)}`;
                    option.dataset.price = pkg.price;
                    packageSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading packages:', error);
            this.showNotification('Error loading packages', 'error');
        }
    }
    
    async loadBookings() {
        try {
            this.showLoading();
            
            const params = new URLSearchParams();
            if (this.currentFilters.status !== 'all') params.append('status', this.currentFilters.status);
            if (this.currentFilters.search) params.append('search', this.currentFilters.search);
            if (this.currentFilters.dateFrom) params.append('date_from', this.currentFilters.dateFrom);
            if (this.currentFilters.dateTo) params.append('date_to', this.currentFilters.dateTo);
            
            const response = await fetch(`../api/walkin/get-all.php?${params}`);
            const result = await response.json();
            
            if (result.success) {
                this.bookings = result.data;
                this.applyFilters();
            } else {
                this.showNotification(result.message || 'Error loading bookings', 'error');
            }
        } catch (error) {
            console.error('Error loading bookings:', error);
            this.showNotification('Error loading bookings', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    applyFilters() {
        // Update current filters
        this.currentFilters.status = document.getElementById('statusFilter').value;
        this.currentFilters.search = document.getElementById('searchFilter').value.toLowerCase();
        this.currentFilters.dateFrom = document.getElementById('dateFrom').value;
        this.currentFilters.dateTo = document.getElementById('dateTo').value;
        
        // Filter bookings
        this.filteredBookings = this.bookings.filter(booking => {
            // Status filter
            if (this.currentFilters.status !== 'all' && booking.status !== this.currentFilters.status) {
                return false;
            }
            
            // Search filter
            if (this.currentFilters.search) {
                const searchMatch = 
                    booking.name.toLowerCase().includes(this.currentFilters.search) ||
                    booking.email.toLowerCase().includes(this.currentFilters.search) ||
                    booking.contact.toLowerCase().includes(this.currentFilters.search) ||
                    booking.package_name.toLowerCase().includes(this.currentFilters.search);
                
                if (!searchMatch) return false;
            }
            
            // Date filter
            if (this.currentFilters.dateFrom) {
                const bookingDate = new Date(booking.created_at).toISOString().split('T')[0];
                if (bookingDate < this.currentFilters.dateFrom) return false;
            }
            
            if (this.currentFilters.dateTo) {
                const bookingDate = new Date(booking.created_at).toISOString().split('T')[0];
                if (bookingDate > this.currentFilters.dateTo) return false;
            }
            
            return true;
        });
        
        // Apply current sort
        this.applySort();
        this.renderTable();
    }
    
    handleSort(sortBy) {
        this.currentSort = sortBy;
        this.applySort();
        this.renderTable();
        
        // Update sort indicators
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        
        const currentTh = document.querySelector(`th[data-sort="${sortBy}"]`);
        if (sortBy.includes('asc')) {
            currentTh.classList.add('sort-asc');
        } else {
            currentTh.classList.add('sort-desc');
        }
    }
    
    applySort() {
        this.filteredBookings.sort((a, b) => {
            switch (this.currentSort) {
                case 'date-asc':
                    return new Date(a.created_at) - new Date(b.created_at);
                case 'date-desc':
                    return new Date(b.created_at) - new Date(a.created_at);
                case 'name-asc':
                    return a.name.localeCompare(b.name);
                case 'name-desc':
                    return b.name.localeCompare(a.name);
                case 'amount-asc':
                    return parseFloat(a.amount) - parseFloat(b.amount);
                case 'amount-desc':
                    return parseFloat(b.amount) - parseFloat(a.amount);
                default:
                    return new Date(b.created_at) - new Date(a.created_at);
            }
        });
    }
    
    // Parse duration string (e.g., "30 Days", "1 Year") to number of days
    parseDurationToDays(durationStr) {
        if (!durationStr) return 0;
        
        const parts = durationStr.toLowerCase().split(' ');
        const value = parseInt(parts[0]);
        const unit = parts[1];
        
        if (isNaN(value)) return 0;
        
        if (unit.includes('day')) return value;
        if (unit.includes('week')) return value * 7;
        if (unit.includes('month')) return value * 30;
        if (unit.includes('year')) return value * 365;
        
        return value;
    }

    // Check if a specific booking is currently active (not expired)
    isBookingActive(booking) {
        if (booking.status !== 'verified') return false;
        
        const now = new Date();
        
        // If backend already provided an expiry date, use it
        if (booking.expires_at) {
            return now <= new Date(booking.expires_at);
        }
        
        // Fallback to client-side calculation
        const bookingDate = new Date(booking.booking_date || booking.created_at);
        const days = this.parseDurationToDays(booking.duration);
        
        if (days === 0) return false;
        
        const expiryDate = new Date(bookingDate);
        expiryDate.setDate(expiryDate.getDate() + days);
        
        return now <= expiryDate;
    }

    renderTable() {
        const tbody = document.getElementById('walkinTableBody');
        const recordCount = document.getElementById('recordCount');
        
        recordCount.textContent = `${this.filteredBookings.length} record${this.filteredBookings.length !== 1 ? 's' : ''}`;
        
        if (this.filteredBookings.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        <i class="fas fa-inbox"></i>
                        <p>No walk-in bookings found</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = this.filteredBookings.map(booking => {
            const isActive = this.isBookingActive(booking);
            return `
            <tr>
                <td>
                    <div class="date-info">
                        <div>${booking.date_formatted}</div>
                        <small class="text-muted">${booking.time_formatted}</small>
                    </div>
                </td>
                <td>
                    <div class="customer-info">
                        <div class="customer-name">${booking.name}</div>
                        <small class="text-muted">${booking.email}</small>
                    </div>
                </td>
                <td>${booking.contact}</td>
                <td>
                    <div class="package-info">
                        <div class="package-name">${booking.package_name}</div>
                        ${booking.status === 'verified' ? `
                            <div style="margin-top: 4px;">
                                <span class="status-badge ${isActive ? 'verified' : 'pending'}" style="padding: 2px 8px; font-size: 0.7rem; display: inline-block;">
                                    ${isActive ? 'Active' : 'Expired'}
                                </span>
                            </div>
                        ` : '<small class="text-muted">Walk-in</small>'}
                    </div>
                </td>
                <td class="amount">${booking.amount_formatted}</td>
                <td>
                    <div class="payment-info">
                        <div class="payment-method">${this.formatPaymentMethod(booking.payment_method)}</div>
                        <small class="payment-status ${booking.payment_status}">${booking.payment_status}</small>
                    </div>
                </td>
                <td>
                    <span class="status-badge ${booking.status}">${booking.status}</span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-primary" onclick="walkinManager.updateStatus(${booking.id}, '${booking.status}')" title="Update Status">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${booking.receipt_full_url ? `
                            <a href="${booking.receipt_full_url}" target="_blank" class="btn btn-sm btn-info" title="View Receipt">
                                <i class="fas fa-receipt"></i>
                            </a>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `}).join('');
    }
    
    formatPaymentMethod(method) {
        const methods = {
            'cash': 'Cash',
            'card': 'Card',
            'gcash': 'GCash',
            'paymaya': 'PayMaya',
            'bank_transfer': 'Bank Transfer'
        };
        return methods[method] || method;
    }
    
    openWalkinModal() {
        document.getElementById('walkinModal').classList.add('active');
        document.getElementById('walkinForm').reset();
        this.setDefaultDate();
        // Reset payment method details display
        this.handlePaymentMethodChange(document.getElementById('paymentMethod').value);
    }
    
    closeWalkinModal() {
        document.getElementById('walkinModal').classList.remove('active');
    }
    
    openStatusModal(bookingId, currentStatus) {
        document.getElementById('statusModal').classList.add('active');
        document.getElementById('statusBookingId').value = bookingId;
        document.getElementById('statusSelect').value = currentStatus;
        document.getElementById('statusNotes').value = '';
    }
    
    closeStatusModal() {
        document.getElementById('statusModal').classList.remove('active');
    }
    
    closeAllModals() {
        this.closeWalkinModal();
        this.closeStatusModal();
    }
    
    async handleWalkinSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        
        try {
            this.showLoading();
            
            const response = await fetch('../api/walkin/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Walk-in booking created successfully!', 'success');
                this.closeWalkinModal();
                this.loadBookings();
            } else {
                this.showNotification(result.message || 'Error creating booking', 'error');
            }
        } catch (error) {
            console.error('Error creating booking:', error);
            this.showNotification('Error creating booking', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    async handleStatusSubmit(e) {
        e.preventDefault();
        
        const bookingId = document.getElementById('statusBookingId').value;
        const newStatus = document.getElementById('statusSelect').value;
        const notes = document.getElementById('statusNotes').value;
        
        try {
            this.showLoading();
            
            const response = await fetch('../api/bookings/update.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: bookingId,
                    status: newStatus,
                    notes: notes
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Booking status updated successfully!', 'success');
                this.closeStatusModal();
                this.loadBookings();
            } else {
                this.showNotification(result.message || 'Error updating status', 'error');
            }
        } catch (error) {
            console.error('Error updating status:', error);
            this.showNotification('Error updating status', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    updateStatus(bookingId, currentStatus) {
        this.openStatusModal(bookingId, currentStatus);
    }
    
    updatePackageInfo() {
        const packageSelect = document.getElementById('packageSelect');
        const selectedOption = packageSelect.options[packageSelect.selectedIndex];
        const price = selectedOption.dataset.price;
        
        if (price) {
            // You can display the price somewhere if needed
            console.log('Selected package price:', price);
        }
    }
    
    resetFilters() {
        document.getElementById('statusFilter').value = 'all';
        document.getElementById('searchFilter').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        
        this.currentFilters = {
            status: 'all',
            search: '',
            dateFrom: '',
            dateTo: ''
        };
        
        this.applyFilters();
    }
    
    showLoading() {
        // Show loading indicator
        const loader = document.createElement('div');
        loader.className = 'loading-overlay';
        loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        document.body.appendChild(loader);
    }
    
    hideLoading() {
        const loader = document.querySelector('.loading-overlay');
        if (loader) {
            loader.remove();
        }
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
}

// Initialize the walk-in bookings manager
const walkinManager = new WalkinBookingsManager();
