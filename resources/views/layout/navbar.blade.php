{{-- resources/views/layout/navbar.blade.php --}}

<div class="naima-navbar">
    <div class="navbar-brand">
        <i class="fas fa-crown"></i>
        {{-- <span>نعيما</span> --}}
    </div>
    <div class="navbar-icons">
        <i class="fas fa-bell"></i>
        <i class="fas fa-user-circle" onclick="openProfileModal()" style="cursor: pointer;"></i>
    </div>
</div>

<!-- ========== موديل الملف الشخصي ========== -->
<div id="profileModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 24px; max-width: 500px; width: 90%; margin: auto; max-height: 90vh; overflow-y: auto;">

        <!-- Header -->
        <div style="background: linear-gradient(135deg, #6c5ce7, #a855f7); padding: 20px; border-radius: 24px 24px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 48px; height: 48px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user-circle" style="font-size: 24px; color: white;"></i>
                </div>
                <div>
                    <h3 style="color: white; margin: 0; font-size: 20px; font-weight: bold;">الملف الشخصي</h3>
                    <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 12px;">عرض وتعديل بيانات حسابك</p>
                </div>
            </div>
            <button onclick="closeProfileModal()" style="background: rgba(255,255,255,0.2); border: none; width: 32px; height: 32px; border-radius: 12px; color: white; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <div style="padding: 24px;">
            <!-- صورة الملف الشخصي -->
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="position: relative; display: inline-block;">
                    <div id="profileImageContainer" style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #6c5ce7, #a855f7); display: flex; align-items: center; justify-content: center; color: white; font-size: 32px; font-weight: bold; cursor: pointer; overflow: hidden;">
                        @php
                            $user = auth()->user();
                            $avatarUrl = $user && method_exists($user, 'getAvatarUrlAttribute') ? $user->getAvatarUrlAttribute() : null;
                            $userName = $user && $user->name ? $user->name : 'مدير';
                            $initials = mb_substr($userName, 0, 2, 'UTF-8');
                        @endphp
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <span id="profileInitial">{{ $initials }}</span>
                        @endif
                    </div>
                    <button type="button" onclick="document.getElementById('profileImageInput').click()" style="position: absolute; bottom: 0; left: 0; background: #6c5ce7; border: none; color: white; width: 28px; height: 28px; border-radius: 50%; cursor: pointer;">
                        <i class="fas fa-camera" style="font-size: 12px;"></i>
                    </button>
                    <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="uploadProfileImage(this)">
                </div>
                <p style="font-size: 12px; color: #666; margin-top: 8px;">انقر على الصورة لتغييرها</p>
            </div>

            <form id="profileForm" method="POST" action="{{ route('admin.profile.update') }}">
                @csrf
                @method('PUT')

                <!-- الاسم -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: bold; margin-bottom: 4px;">الاسم</label>
                    <input type="text" name="name" id="profileName" value="{{ auth()->user()->name ?? '' }}"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 12px;">
                    <div id="nameError" style="color: red; font-size: 12px; margin-top: 4px; display: none;"></div>
                </div>

                <!-- رقم الهاتف -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: bold; margin-bottom: 4px;">رقم الهاتف</label>
                    <input type="tel" name="phone" id="profilePhone" value="{{ auth()->user()->phone ?? '' }}"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 12px;" dir="ltr">
                    <div id="phoneError" style="color: red; font-size: 12px; margin-top: 4px; display: none;"></div>
                </div>

                <!-- كلمة المرور الجديدة -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: bold; margin-bottom: 4px;">كلمة المرور الجديدة</label>
                    <input type="password" name="password" id="profilePassword" placeholder="اتركها فارغة إذا لم تريد التغيير"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 12px;">
                    <div id="passwordError" style="color: red; font-size: 12px; margin-top: 4px; display: none;"></div>
                </div>

                <!-- تأكيد كلمة المرور -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: bold; margin-bottom: 4px;">تأكيد كلمة المرور</label>
                    <input type="password" name="password_confirmation" id="profilePasswordConfirmation" placeholder="أعد كتابة كلمة المرور"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 12px;">
                </div>

                <!-- أزرار الإجراءات -->
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" style="flex: 1; background: linear-gradient(135deg, #6c5ce7, #a855f7); color: white; padding: 12px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer;">
                        <i class="fas fa-save"></i> حفظ التغييرات
                    </button>
                    <button type="button" onclick="closeProfileModal()" style="flex: 1; background: #f0f0f0; color: #333; padding: 12px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer;">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- رسالة نجاح -->
<div id="profileToast" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10001; display: none;">
    <div style="background: #22c55e; color: white; padding: 12px 24px; border-radius: 50px;">
        <i class="fas fa-check-circle"></i> <span id="toastMessage">تم حفظ التغييرات</span>
    </div>
</div>

<script>
    function openProfileModal() {
        const modal = document.getElementById('profileModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeProfileModal() {
        const modal = document.getElementById('profileModal');
        if (modal) {
            modal.style.display = 'none';
        }
        // إخفاء رسائل الخطأ
        document.querySelectorAll('[id$="Error"]').forEach(el => {
            if (el) el.style.display = 'none';
        });
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('profileToast');
        if (!toast) return;

        const toastMessage = document.getElementById('toastMessage');
        const toastDiv = toast.querySelector('div');

        if (toastMessage) toastMessage.innerText = message;

        if (toastDiv) {
            if (type === 'error') {
                toastDiv.style.background = '#ef4444';
            } else {
                toastDiv.style.background = '#22c55e';
            }
        }

        toast.style.display = 'block';
        setTimeout(() => {
            if (toast) toast.style.display = 'none';
        }, 3000);
    }

    function uploadProfileImage(input) {
        if (!input || !input.files || !input.files[0]) return;

        const file = input.files[0];
        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

        const container = document.getElementById('profileImageContainer');
        if (!container) return;

        const originalContent = container.innerHTML;
        container.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;"><i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i></div>';

        fetch('{{ route("admin.profile.upload-image") }}', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const img = document.createElement('img');
                img.src = data.image_url + '?t=' + new Date().getTime();
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                container.innerHTML = '';
                container.appendChild(img);
                showToast('تم تحديث الصورة بنجاح');
            } else {
                container.innerHTML = originalContent;
                showToast(data.message || 'حدث خطأ', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = originalContent;
            showToast('حدث خطأ في رفع الصورة', 'error');
        });
    }

    // التأكد من وجود النموذج قبل إضافة المستمع
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // التحقق من وجود العناصر قبل قراءة قيمها
            const nameInput = document.getElementById('profileName');
            const phoneInput = document.getElementById('profilePhone');
            const passwordInput = document.getElementById('profilePassword');
            const passwordConfirmationInput = document.getElementById('profilePasswordConfirmation');

            const password = passwordInput ? passwordInput.value : '';
            const passwordConfirmation = passwordConfirmationInput ? passwordConfirmationInput.value : '';

            if (password !== passwordConfirmation) {
                showToast('كلمة المرور وتأكيدها غير متطابقين', 'error');
                return;
            }

            const formData = {
                _method: 'PUT',
                name: nameInput ? nameInput.value : '',
                phone: phoneInput ? phoneInput.value : '',
                password: password
            };

            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('تم حفظ التغييرات بنجاح');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    if (data.errors) {
                        for (const [key, errors] of Object.entries(data.errors)) {
                            const errorDiv = document.getElementById(`${key}Error`);
                            if (errorDiv) {
                                errorDiv.innerText = errors[0];
                                errorDiv.style.display = 'block';
                            }
                        }
                    }
                    showToast(data.message || 'حدث خطأ في حفظ البيانات', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('حدث خطأ في الاتصال بالخادم', 'error');
            });
        });
    }

    // إغلاق الموديل عند الضغط على ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('profileModal');
            if (modal && modal.style.display === 'flex') {
                closeProfileModal();
            }
        }
    });
</script>
