/**
 * Branding Interface JavaScript
 * Enhanced functionality for the branding settings page
 */

class BrandingInterface {
    constructor() {
        this.initializeComponents();
        this.setupEventListeners();
        this.setupDragAndDrop();
    }

    initializeComponents() {
        this.logoPreview = document.getElementById('logo-preview');
        this.logoInput = document.querySelector('input[name="logo"]');
        this.colorInputs = document.querySelectorAll('input[type="color"]');
        this.form = document.querySelector('form[action*="update_branding"]');
    }

    setupEventListeners() {
        // Color picker synchronization
        this.colorInputs.forEach(colorInput => {
            const textInput = colorInput.parentElement.querySelector('input[type="text"]');
            
            colorInput.addEventListener('change', (e) => {
                if (textInput) {
                    textInput.value = e.target.value;
                }
                this.updateColorPreview();
                this.updateBrandPreview();
            });

            if (textInput) {
                textInput.addEventListener('input', (e) => {
                    if (this.isValidHexColor(e.target.value)) {
                        colorInput.value = e.target.value;
                        this.updateColorPreview();
                        this.updateBrandPreview();
                    }
                });
            }
        });

        // Logo input change
        if (this.logoInput) {
            this.logoInput.addEventListener('change', (e) => {
                this.handleLogoUpload(e.target.files[0]);
            });
        }

        // Form submission with loading state
        if (this.form) {
            this.form.addEventListener('submit', (e) => {
                this.showSavingState();
            });
        }

        // Real-time preview updates
        const textInputs = document.querySelectorAll('input[type="text"], textarea');
        textInputs.forEach(input => {
            input.addEventListener('input', () => {
                this.updateBrandPreview();
            });
        });
    }

    setupDragAndDrop() {
        if (!this.logoPreview) return;

        this.logoPreview.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.logoPreview.classList.add('dragover');
        });

        this.logoPreview.addEventListener('dragleave', (e) => {
            e.preventDefault();
            this.logoPreview.classList.remove('dragover');
        });

        this.logoPreview.addEventListener('drop', (e) => {
            e.preventDefault();
            this.logoPreview.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && this.isImageFile(files[0])) {
                this.handleLogoUpload(files[0]);
                // Update the file input
                if (this.logoInput) {
                    const dt = new DataTransfer();
                    dt.items.add(files[0]);
                    this.logoInput.files = dt.files;
                }
            }
        });

        // Make logo preview clickable to trigger file input
        this.logoPreview.addEventListener('click', () => {
            if (this.logoInput) {
                this.logoInput.click();
            }
        });
    }

    handleLogoUpload(file) {
        if (!file || !this.isImageFile(file)) {
            this.showToast('Please select a valid image file (JPG, PNG, GIF, SVG, WebP)', 'error');
            return;
        }

        if (file.size > 5242880) { // 5MB
            this.showToast('File size must be less than 5MB', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            this.updateLogoPreview(e.target.result);
        };
        reader.readAsDataURL(file);
    }

    updateLogoPreview(src) {
        if (this.logoPreview) {
            this.logoPreview.innerHTML = `
                <img src="${src}" alt="Logo Preview" class="w-full h-full object-contain">
                <div class="logo-preview-overlay">
                    <div>
                        <svg class="mx-auto h-6 w-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                        <p>Click to change</p>
                    </div>
                </div>
            `;
        }
    }

    updateColorPreview() {
        const primaryColor = document.querySelector('input[name="primary_color"]')?.value;
        const secondaryColor = document.querySelector('input[name="secondary_color"]')?.value;
        const accentColor = document.querySelector('input[name="accent_color"]')?.value;
        
        const previewBars = document.querySelectorAll('.color-preview-bar');
        if (previewBars.length >= 3) {
            if (primaryColor) previewBars[0].style.background = primaryColor;
            if (secondaryColor) previewBars[1].style.background = secondaryColor;
            if (accentColor) previewBars[2].style.background = accentColor;
        }

        // Update CSS custom properties for real-time preview
        if (primaryColor) document.documentElement.style.setProperty('--brand-primary', primaryColor);
        if (secondaryColor) document.documentElement.style.setProperty('--brand-secondary', secondaryColor);
        if (accentColor) document.documentElement.style.setProperty('--brand-accent', accentColor);
    }

    updateBrandPreview() {
        const businessName = document.querySelector('input[name="business_name"]')?.value || 'Your Business Name';
        const tagline = document.querySelector('input[name="tagline"]')?.value || 'Your tagline here';
        const website = document.querySelector('input[name="website"]')?.value || 'https://yourwebsite.com';
        
        let brandPreview = document.querySelector('.brand-preview');
        if (!brandPreview) {
            // Create brand preview if it doesn't exist
            const container = document.querySelector('.branding-card:last-child');
            if (container) {
                brandPreview = document.createElement('div');
                brandPreview.className = 'brand-preview';
                container.appendChild(brandPreview);
            }
        }

        if (brandPreview) {
            brandPreview.innerHTML = `
                <h4>${this.escapeHtml(businessName)}</h4>
                <p>${this.escapeHtml(tagline)}</p>
                <p class="text-sm mt-2 opacity-75">${this.escapeHtml(website)}</p>
            `;
        }
    }

    showSavingState() {
        const submitButton = this.form?.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.classList.add('saving-state');
            submitButton.disabled = true;
            
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Saving...
            `;

            // Reset state after 5 seconds (in case of slow network)
            setTimeout(() => {
                if (submitButton.classList.contains('saving-state')) {
                    submitButton.classList.remove('saving-state');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            }, 5000);
        }
    }

    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }

    isImageFile(file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
        return allowedTypes.includes(file.type);
    }

    isValidHexColor(hex) {
        return /^#[0-9A-F]{6}$/i.test(hex);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new BrandingInterface();
});

// Export for potential use in other scripts
window.BrandingInterface = BrandingInterface;