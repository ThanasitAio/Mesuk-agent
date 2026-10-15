{{-- popup + JS หน้าจดมิเตอร์ ใช้ร่วมกัน: มิเตอร์ย่อย (meters/show) + มิเตอร์หลัก (master-meters/show)
     include หลังฟอร์ม id="meterReadingForm" - แต่ละแถวมิเตอร์ใช้ x-data="meterReadingRow()" ครอบ meters/partials/reading-fields
     แถวที่ไม่มี data-price-per-unit (มิเตอร์หลัก) สรุปสดแสดงแค่จำนวนหน่วย ไม่แสดงยอดเงิน

     กันกรอกผิด (ผู้ใช้ขอ 2026-10-12, ล้อ happyest admin/meter-readings/partials/reading-scripts) - กติกาต้องตรงกับ MeterReading::checkInput():
     - ช่องเลขมิเตอร์รับแต่ตัวเลข ตัดที่ N หลัก (ไม่ใช้ maxlength เพราะวาง "12,345" แล้วถูกตัดก่อนกรอง) ต้องครบ N หลักพอดี
     - ออกจากช่องแล้วผิด -> popup กลางจอทันที / เลขน้อยกว่าเดือนก่อน -> popup ให้เลือก "แก้เลข" หรือ "มิเตอร์เริ่มนับใหม่"
     - กดบันทึก -> ตรวจทุกแถว ผิด = popup รายการเดียว + กรอบแดง + โฟกัสช่องแรก, น่าสงสัย = popup ยืนยันก่อนส่ง
     - ค่าเริ่มต้นที่ระบบเติม (สีเหลือง) ถูกแก้แล้วเป็นสีขาว

     2026-10-14 (เหมือนแอดมิน): กรอกเลขปัจจุบันแล้วต้องมีรูปหน้าปัด (เลือกใหม่ หรือรูปที่บันทึกไว้แล้ว) และต้องเลือกวันที่อ่าน
     - เว้นเลขปัจจุบันว่าง = ข้ามมิเตอร์นี้ (isSkipped) ไม่บังคับช่องอื่น แถวที่บันทึกไว้แล้วล้างเลขทิ้งไม่ได้
       2026-10-15 (ผู้ใช้ขอ - ต่างจากแอดมินโดยตั้งใจ): ไม่ถามยืนยันตอนข้าม และว่างทุกมิเตอร์ก็กดบันทึกได้ (ไม่บันทึกอะไร)
     - ช่องรูป photos[main|restart]: main = รูปหน้าปัด, restart = รูปเลขสุดท้ายก่อนเริ่มนับใหม่ (ไม่บังคับ, ปิดสวิตช์ = disable)
     - เลือกรูปแล้ว: ไม่ใช่ JPG/PNG -> popup บอกชนิดไฟล์, ย่อด้วย meterShrinkImage ก่อนตรวจขนาด, เกิน 10MB -> popup
     - บันทึกไม่สำเร็จแล้วกลับมาหน้านี้ เบราว์เซอร์ไม่เก็บไฟล์ที่เลือกไว้ -> popup บอกให้เลือกรูปใหม่ด้วย --}}

{{-- popup กลางจอ (มือถือก็อยู่กลาง ไม่ใช่ bottom sheet แบบ x-modal) - อยู่เหนือแถบปุ่มบันทึกลอยและ lightbox รูป --}}
<div id="meter-dialog" class="hidden fixed inset-0 z-[80] flex items-center justify-center p-4"
     role="alertdialog" aria-modal="true" aria-labelledby="meter-dialog-title" aria-describedby="meter-dialog-body">
    <div data-dialog-backdrop class="absolute inset-0 bg-black/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div data-dialog-panel class="relative w-full max-w-sm max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl opacity-0 scale-95 transition duration-200 ease-out">
        <div class="px-5 pt-6 pb-4 text-center overflow-y-auto">
            <div data-dialog-icon class="mx-auto w-12 h-12 rounded-full flex items-center justify-center mb-3"></div>
            <h3 id="meter-dialog-title" data-dialog-title class="text-base font-bold text-gray-800 leading-snug"></h3>
            <div id="meter-dialog-body" data-dialog-body class="mt-2 text-sm text-gray-600 leading-relaxed"></div>
        </div>
        <div class="px-5 pb-5 pt-1 flex flex-col-reverse sm:flex-row gap-2">
            <button type="button" data-dialog-cancel
                    class="w-full sm:flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors"></button>
            <button type="button" data-dialog-ok
                    class="w-full sm:flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 transition-colors"></button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const METER_IMAGE_MAX_BYTES = 10 * 1024 * 1024; // ต้องตรงกับ max:10240 ใน store() ของ MeterReadingController / MasterMeterReadingController
    const METER_SERVER_ERRORS = @json((object) $errors->getMessages());
    const METER_NOTICE = @json(session('meter_notice'));
    const METER_WHAT = {
        previous: 'เลขมิเตอร์เดือนก่อน',
        current: 'เลขมิเตอร์ปัจจุบัน',
        oldFinal: 'เลขสุดท้ายก่อนเริ่มนับใหม่',
        newStart: 'เลขเริ่มต้นใหม่',
    };

    const meterForm = document.getElementById('meterReadingForm');
    const meterRows = [];
    const meterPendingChecks = [];
    let meterDirty = false;          // ฟอร์มถูกแก้แล้วยังไม่บันทึก
    let meterLeaving = false;        // กำลังส่งฟอร์ม/ยืนยันออกจากหน้าแล้ว - ไม่ต้องถามซ้ำ
    let meterSubmitIntentAt = 0;     // เพิ่งกดปุ่มบันทึก - ไม่ต้องเด้ง popup ของช่องที่เพิ่งออก เพราะ submit จะแสดงรายการรวมให้
    let meterLastSubmitter = null;

    function meterFmt(n) {
        return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function meterFmtUnits(n) {
        return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });
    }

    function meterFocus(el) {
        if (! el) return;
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        el.focus({ preventScroll: true });
    }

    function meterByScreenOrder(a, b) {
        if (a.el === b.el) return 0;
        if (! a.el) return -1;
        if (! b.el) return 1;
        return (a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1;
    }

    // ย่อรูป JPEG/PNG ให้ด้านยาวไม่เกิน maxPx ก่อนตรวจขนาด (พอร์ตจาก happyest public/assets/js/image-shrink.js)
    // รูปจากกล้องมือถือ 5-20MB ย่อแล้วเหลือไม่กี่ร้อย KB - ไม่เคย reject: ย่อไม่ได้/ผลใหญ่กว่าเดิม คืนไฟล์เดิม
    function meterShrinkImage(file, maxPx, quality) {
        if (! file || ! /^image\/(jpeg|jpg|png|webp)$/i.test(file.type || '') || file.size < 300 * 1024) {
            return Promise.resolve(file);
        }
        return new Promise(function (resolve) {
            const url = URL.createObjectURL(file);
            let finished = false;
            const timer = setTimeout(function () { finish(file); }, 20000);
            function finish(result) {
                if (finished) return;
                finished = true;
                clearTimeout(timer);
                URL.revokeObjectURL(url);
                resolve(result);
            }
            const img = new Image();
            img.onload = function () {
                try {
                    const scale = Math.min(1, maxPx / (Math.max(img.naturalWidth, img.naturalHeight) || 1));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
                    canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
                    const ctx = canvas.getContext('2d');
                    // PNG โปร่งใส -> JPEG จะกลายเป็นพื้นดำ ถ้าไม่ลงพื้นขาวก่อน
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob(function (blob) {
                        canvas.width = 0;
                        canvas.height = 0;
                        if (! blob || blob.size >= file.size) { finish(file); return; }
                        try {
                            finish(new File([blob], (file.name || 'image').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
                        } catch (e) { finish(file); }
                    }, 'image/jpeg', quality);
                } catch (e) { finish(file); }
            };
            img.onerror = function () { finish(file); };
            img.src = url;
        });
    }

    // ---- popup กลางจอ: MeterDialog.open({...}) คืน Promise<boolean> (true = ปุ่มหลัก) ----
    const MeterDialog = (() => {
        const ICONS = {
            error:   { wrap: 'bg-red-100 text-red-600', dot: 'bg-red-400', path: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
            warning: { wrap: 'bg-amber-100 text-amber-600', dot: 'bg-amber-400', path: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z' },
            info:    { wrap: 'bg-blue-100 text-blue-600', dot: 'bg-blue-400', path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        };
        const OK_VARIANTS = {
            brand: 'bg-brand-600 hover:bg-brand-700',
            danger: 'bg-red-600 hover:bg-red-700',
        };
        const OK_BASE = 'w-full sm:flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-colors ';

        let el = null;
        let current = null;
        let resolver = null;

        function nodes() {
            if (el) return el;
            const root = document.getElementById('meter-dialog');
            el = {
                root: root,
                backdrop: root.querySelector('[data-dialog-backdrop]'),
                panel: root.querySelector('[data-dialog-panel]'),
                icon: root.querySelector('[data-dialog-icon]'),
                title: root.querySelector('[data-dialog-title]'),
                body: root.querySelector('[data-dialog-body]'),
                ok: root.querySelector('[data-dialog-ok]'),
                cancel: root.querySelector('[data-dialog-cancel]'),
            };
            el.ok.addEventListener('click', () => finish(true));
            el.cancel.addEventListener('click', () => finish(false));
            // คลิกนอกกล่อง: ปิดได้เฉพาะ popup แจ้งเตือน (ปุ่มเดียว) - popup ที่ต้องเลือกต้องกดปุ่มเท่านั้น
            el.backdrop.addEventListener('click', () => { if (current && ! current.cancelText) finish(true); });
            root.addEventListener('keydown', (e) => {
                if (! current) return;
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    finish(current.cancelText ? false : true);
                } else if (e.key === 'Tab') {
                    // วนโฟกัสอยู่ในปุ่มของ popup
                    const buttons = [el.cancel, el.ok].filter(b => ! b.classList.contains('hidden'));
                    const i = buttons.indexOf(document.activeElement);
                    e.preventDefault();
                    buttons[(i + (e.shiftKey ? -1 : 1) + buttons.length) % buttons.length].focus();
                }
            });
            return el;
        }

        // ปฏิทินเลือกวันที่ (flatpickr) อยู่ชั้นบนกว่า popup - เปิดค้างไว้จะทับ popup จึงปิดก่อน
        function closePickers() {
            document.querySelectorAll('[x-ref="fpWrapper"]').forEach((w) => {
                try { w._flatpickr && w._flatpickr.close(); } catch (e) {}
            });
        }

        function render(o) {
            const n = nodes();
            const icon = ICONS[o.icon] || ICONS.error;
            n.icon.className = 'mx-auto w-12 h-12 rounded-full flex items-center justify-center mb-3 ' + icon.wrap;
            n.icon.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' + icon.path + '"/></svg>';
            n.title.textContent = o.title || '';

            n.body.replaceChildren();
            if (o.text) {
                const p = document.createElement('p');
                p.textContent = o.text;
                n.body.appendChild(p);
            }
            if (o.list && o.list.length) {
                const ul = document.createElement('ul');
                ul.className = 'mt-1 text-left space-y-1.5';
                o.list.forEach((text) => {
                    const li = document.createElement('li');
                    li.className = 'flex items-start gap-2';
                    const dot = document.createElement('span');
                    dot.className = 'mt-2 w-1.5 h-1.5 rounded-full flex-shrink-0 ' + icon.dot;
                    const span = document.createElement('span');
                    span.textContent = text;
                    li.append(dot, span);
                    ul.appendChild(li);
                });
                n.body.appendChild(ul);
            }
            if (o.hint) {
                const p = document.createElement('p');
                p.className = 'mt-2 text-xs text-gray-400';
                p.textContent = o.hint;
                n.body.appendChild(p);
            }

            n.ok.textContent = o.okText;
            n.ok.className = OK_BASE + (OK_VARIANTS[o.okVariant] || OK_VARIANTS.brand);
            n.cancel.textContent = o.cancelText || '';
            n.cancel.classList.toggle('hidden', ! o.cancelText);
        }

        function open(opts) {
            if (current) finish(current.cancelText ? false : true, true);
            current = Object.assign({
                icon: 'error', title: '', text: '', list: null, hint: '',
                okText: 'ตกลง', okVariant: 'brand', cancelText: null, focusCancel: false, focus: null,
            }, opts || {});

            closePickers();
            // ปิดคีย์บอร์ดมือถือก่อน popup ขึ้น
            if (document.activeElement && document.activeElement.blur) document.activeElement.blur();

            const n = nodes();
            render(current);
            n.root.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => {
                n.backdrop.classList.replace('opacity-0', 'opacity-100');
                n.panel.classList.remove('opacity-0', 'scale-95');
                n.panel.classList.add('opacity-100', 'scale-100');
                (current && current.cancelText && current.focusCancel ? n.cancel : n.ok).focus();
            });

            return new Promise((resolve) => { resolver = resolve; });
        }

        // replaced = ถูก popup ใหม่แทนที่ - ไม่ต้องย้ายโฟกัส
        function finish(result, replaced) {
            if (! current) return;
            const opts = current;
            const resolve = resolver;
            current = null;
            resolver = null;

            const n = nodes();
            n.backdrop.classList.replace('opacity-100', 'opacity-0');
            n.panel.classList.remove('opacity-100', 'scale-100');
            n.panel.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                if (current) return; // มี popup ใหม่เปิดต่อทันที
                n.root.classList.add('hidden');
                document.body.style.overflow = '';
                if (opts.focus && ! replaced) meterFocus(opts.focus);
            }, 200);

            resolve && resolve(result);
        }

        return { open: open, isOpen: () => current !== null };
    })();

    // ข้อผิดพลาดหลายข้อ = รายการเดียว (เรียงตามจอ) / ข้อเดียว = ข้อความเต็ม - ปิดแล้วโฟกัสช่องแรกที่ต้องแก้
    function meterShowProblems(problems) {
        if (! problems.length) return Promise.resolve();
        const one = problems.length === 1;
        return MeterDialog.open({
            icon: 'error',
            title: one ? problems[0].title : 'บันทึกไม่ได้ ' + problems.length + ' รายการ',
            text: one ? problems[0].text : '',
            list: one ? null : problems.map(p => p.list),
            hint: one ? '' : 'ช่องที่ต้องแก้มีกรอบสีแดง กด "ตกลง" แล้วระบบจะพาไปช่องแรก',
            focus: problems[0].el || null,
        });
    }

    // ---- มิเตอร์ 1 ตัว: x-data="meterReadingRow()" ครอบ meters/partials/reading-fields ----
    // ค่าตั้งต้นอ่านจาก data-* ของ [data-meter-reading] และ value ที่เซิร์ฟเวอร์ render ไว้ (init รันก่อน x-model ของลูก)
    function meterReadingRow() {
        let box = null; // เก็บนอก state ของ Alpine - element ห้ามถูกห่อด้วย reactive proxy

        return {
            digits: 5,
            max: 99999,
            label: 'มิเตอร์',
            example: '02345',
            readOnly: false,
            lockedPrevious: null,
            savedCurrent: null,
            prevUnits: null,
            next: null,
            pricePerUnit: null,

            v: { previous: '', current: '', oldFinal: '', newStart: '' },
            def: { previous: false, current: false, oldFinal: false, newStart: false },
            invalid: { previous: false, current: false, oldFinal: false, newStart: false },
            changed: false,
            meterId: null,

            // ช่องรูป: saved = รูปที่บันทึกไว้แล้ว (นับว่ามีรูป), url = รูปที่เพิ่งเลือก, busy = กำลังย่อรูป
            photos: {
                main: { present: false, saved: '', url: null, picked: false, busy: false, token: 0, invalid: false },
                restart: { present: false, saved: '', url: null, picked: false, busy: false, token: 0, invalid: false },
            },
            lightboxSrc: null,

            init() {
                box = this.$el.querySelector('[data-meter-reading]');
                if (! box) return;
                const d = box.dataset;
                const num = (s) => (s === undefined || s === '') ? null : Number(s);

                this.digits = Number(d.digits) || 5;
                this.max = Number(d.max) || 99999;
                this.label = d.label || 'มิเตอร์';
                this.example = this.digits === 4 ? '0546' : '02345';
                this.readOnly = d.readonly === '1';
                this.changed = d.changed === '1';
                this.lockedPrevious = num(d.lockedPrevious);
                this.savedCurrent = num(d.savedCurrent);
                this.prevUnits = num(d.prevUnits);
                this.pricePerUnit = num(d.pricePerUnit);
                this.next = d.nextPrevious !== undefined
                    ? { previous: Number(d.nextPrevious), period: d.nextPeriod, locked: d.nextLocked === '1' }
                    : null;
                this.meterId = d.meterId || null;

                box.querySelectorAll('input[data-field]').forEach((input) => {
                    const f = input.dataset.field;
                    this.v[f] = input.value;
                    this.def[f] = input.dataset.isDefault === '1';
                    this.invalid[f] = input.getAttribute('aria-invalid') === 'true';
                });

                box.querySelectorAll('[data-photo-slot]').forEach((slot) => {
                    const p = this.photos[slot.dataset.photoSlot];
                    if (! p) return;
                    p.present = true;
                    p.saved = slot.dataset.savedSrc || '';
                    p.invalid = slot.querySelector('[data-photo-pick]')?.dataset.photoInvalid === '1';
                });

                const dateEl = this.dateInput();
                if (dateEl) {
                    dateEl.addEventListener('change', () => {
                        const trigger = this.focusTarget('date');
                        trigger && trigger.classList.remove('ring-2', 'ring-red-400');
                        meterMarkDirty();
                        this.checkLater('date');
                    });
                }

                meterRows.push(this);
            },

            input(f) { return box ? box.querySelector('input[data-field="' + f + '"]') : null; },
            dateInput() { return box ? box.querySelector('input[name$="[reading_date]"]') : null; },
            changedBtn() { return box ? box.querySelector('[data-changed-btn]') : null; },
            photoInput(k) { return box ? box.querySelector('input[data-photo-input="' + k + '"]') : null; },
            photoPick(k) { return box ? box.querySelector('[data-photo-slot="' + k + '"] [data-photo-pick]') : null; },
            focusTarget(f) {
                if (f === 'date') return box ? box.querySelector('[data-date-field] [data-toggle]') : null;
                if (f === 'photo') return this.photoPick('main');
                return this.input(f);
            },

            // ---- ช่องรูป ----
            photoDisabled(k) { return this.readOnly || (k === 'restart' && ! this.changed); },
            photoSrc(k) { return this.photos[k].url || this.photos[k].saved || ''; },
            hasPhoto(k) { return this.photos[k].picked || !! this.photos[k].saved; },
            photoLabel(k) {
                const p = this.photos[k];
                if (p.busy) return 'กำลังเตรียมรูป...';
                return (p.picked || p.saved) ? 'เปลี่ยนรูป' : 'เลือกรูป';
            },
            pickPhoto(k) {
                const input = this.photoInput(k);
                if (input && ! input.disabled) input.click();
            },
            // ยกเลิกรูปที่เพิ่งเลือก กลับไปรูปที่บันทึกไว้ (ลบรูปที่บันทึกไว้แล้วไม่ได้ ให้เลือกรูปใหม่แทน)
            resetPhoto(k) {
                const p = this.photos[k];
                p.token++;
                p.picked = false;
                p.busy = false;
                const input = this.photoInput(k);
                if (input) input.value = '';
                if (p.url) { URL.revokeObjectURL(p.url); p.url = null; }
            },
            rejectPhoto(k, title, text) {
                this.resetPhoto(k);
                this.photos[k].invalid = true;
                MeterDialog.open({ icon: 'error', title: title, text: this.label + ': ' + text, focus: this.photoPick(k) });
            },
            // ตรวจชนิดไฟล์ -> ย่อ -> ตรวจขนาด -> พรีวิว
            onPhotoChange(k, e) {
                const input = e.target;
                const file = input.files && input.files[0];
                if (! file) { this.resetPhoto(k); return; } // ปิดหน้าต่างเลือกไฟล์โดยไม่เลือก (บางเบราว์เซอร์ล้างไฟล์เดิม)
                const p = this.photos[k];
                const what = k === 'restart' ? 'รูปเลขสุดท้ายก่อนเริ่มนับใหม่' : 'รูปมิเตอร์';
                p.invalid = false;

                const ext = (file.name.split('.').pop() || '').toLowerCase();
                const typeOk = /^image\/(jpeg|png)$/i.test(file.type || '') || (! file.type && /^(jpe?g|png)$/.test(ext));
                if (! typeOk) {
                    this.rejectPhoto(k, 'ไฟล์รูปไม่รองรับ', what + 'ต้องเป็น JPG หรือ PNG (ไฟล์ที่เลือกเป็น ' + (ext ? ext.toUpperCase() : 'ไฟล์ชนิดอื่น') + ') เลือกรูปใหม่');
                    return;
                }

                const token = ++p.token;
                p.busy = true;
                meterShrinkImage(file, 2048, 0.85).catch(() => file).then((out) => {
                    if (token !== p.token) return; // เลือกรูปอื่น/ยกเลิกระหว่างย่อ
                    p.busy = false;

                    let use = file;
                    if (out && out !== file) {
                        try {
                            const dt = new DataTransfer();
                            dt.items.add(out);
                            input.files = dt.files;
                            use = out;
                        } catch (err) { /* เบราว์เซอร์แทนไฟล์ใน input ไม่ได้ - ส่งไฟล์เดิม */ }
                    }
                    if (use.size > METER_IMAGE_MAX_BYTES) {
                        this.rejectPhoto(k, 'รูปใหญ่เกิน 10MB', what + ' ' + (use.size / 1048576).toFixed(1) + 'MB เลือกรูปที่เล็กกว่านี้');
                        return;
                    }

                    if (p.url) URL.revokeObjectURL(p.url);
                    p.url = URL.createObjectURL(use);
                    p.picked = true;
                    meterMarkDirty();
                });
            },
            busy() { return this.photos.main.busy || this.photos.restart.busy; },

            // เว้นเลขปัจจุบันว่างในแถวที่ยังไม่เคยบันทึก = ข้ามมิเตอร์นี้งวดนี้
            isSkipped() {
                return ! this.readOnly && this.v.current === '' && this.savedCurrent === null;
            },

            pad(n) { return String(n).padStart(this.digits, '0'); },

            // ช่องนี้แสดงอยู่และถูกส่งไปกับฟอร์ม
            present(f) {
                if (f === 'previous') return this.lockedPrevious === null && !! this.input('previous');
                if (f === 'oldFinal' || f === 'newStart') return this.changed;
                return true;
            },
            numberOf(f) {
                return this.present(f) && this.v[f].length === this.digits ? Number(this.v[f]) : null;
            },
            previousValue() {
                return this.lockedPrevious !== null ? this.lockedPrevious : this.numberOf('previous');
            },
            filled(f) {
                if (f === 'date') {
                    const d = this.dateInput();
                    return !! (d && d.value);
                }
                return this.v[f] !== '';
            },

            get hasCurrent() { return this.numberOf('current') !== null; },

            // พรีวิวจำนวนหน่วยแบบเรียลไทม์ - สูตรเดียวกับ MeterReading::calculateUnits() (ไม่มีกรณีรีเซ็ตแล้ว)
            get liveUnits() {
                const current = this.numberOf('current') ?? 0;
                const previous = this.previousValue();
                let units;
                if (this.changed) {
                    units = ((this.numberOf('oldFinal') ?? 0) - (previous ?? 0)) + (current - (this.numberOf('newStart') ?? 0));
                } else if (previous === null) {
                    units = current;
                } else {
                    units = current - previous;
                }
                return Math.max(units, 0);
            },
            get liveSummary() {
                return meterFmt(this.liveUnits) + ' หน่วย'
                    + (this.pricePerUnit !== null ? ' · ฿' + meterFmt(this.liveUnits * this.pricePerUnit) : '');
            },
            get calcWarning() {
                const previous = this.previousValue();
                const current = this.numberOf('current');
                return ! this.changed && previous !== null && current !== null && current < previous;
            },
            get calcLine() {
                const previous = this.previousValue();
                const current = this.numberOf('current');
                if (current === null) return '';

                let text = '';
                if (this.changed) {
                    const oldFinal = this.numberOf('oldFinal');
                    const newStart = this.numberOf('newStart');
                    if (previous !== null && oldFinal !== null && newStart !== null) {
                        text = '(' + this.pad(oldFinal) + ' - ' + this.pad(previous) + ') + (' + this.pad(current) + ' - ' + this.pad(newStart) + ') = ' + meterFmtUnits(this.liveUnits) + ' หน่วย';
                    }
                } else if (previous !== null) {
                    if (current < previous) {
                        return 'เลขปัจจุบันน้อยกว่าเดือนก่อน ' + meterFmtUnits(previous - current) + ' หน่วย - ตรวจเลข หรือกด "มิเตอร์เริ่มนับใหม่"';
                    }
                    text = this.pad(current) + ' - ' + this.pad(previous) + ' = ' + meterFmtUnits(this.liveUnits) + ' หน่วย';
                }
                if (text && this.prevUnits !== null) {
                    text += ' · เดือนก่อนใช้ ' + meterFmtUnits(this.prevUnits) + ' หน่วย';
                }
                return text;
            },

            // พิมพ์ในช่องเลข: รับแต่ตัวเลข (เลขไทยแปลงเป็นอารบิก) ตัดที่ N หลัก - แก้แล้ว = ไม่ใช่ค่าเริ่มต้นอีกต่อไป -> สีขาว
            onDigits(f, e) {
                const el = e.target;
                const clean = el.value
                    .replace(/[๐-๙]/g, c => String(c.charCodeAt(0) - 0x0E50))
                    .replace(/\D+/g, '')
                    .slice(0, this.digits);
                if (clean !== el.value) el.value = clean;
                this.v[f] = clean;
                this.def[f] = false;
                this.invalid[f] = false;
                meterMarkDirty();
            },

            // เปิดสวิตช์ + ช่องว่าง -> เติมค่าเริ่มต้น (ไฟ 99999 / น้ำ 9999 และ 0) เป็นสีเหลือง
            setChanged(on) {
                this.changed = on;
                if (on) {
                    ['oldFinal', 'newStart'].forEach((f) => {
                        const el = this.input(f);
                        if (this.v[f] === '' && el && el.dataset.default) {
                            this.v[f] = el.dataset.default;
                            this.def[f] = true;
                        }
                    });
                } else {
                    this.invalid.oldFinal = false;
                    this.invalid.newStart = false;
                }
                meterMarkDirty();
            },
            toggleChanged() {
                this.setChanged(! this.changed);
            },

            markInvalid(f) {
                if (f === 'date') {
                    const trigger = this.focusTarget('date');
                    trigger && trigger.classList.add('ring-2', 'ring-red-400');
                } else if (f === 'photo') {
                    this.photos.main.invalid = true;
                } else if (f in this.invalid) {
                    this.invalid[f] = true;
                }
            },

            // ปัญหาที่ต้องแก้ก่อนบันทึก (ตรงกับ MeterReading::checkInput()) - fields = ช่องที่เกี่ยวข้อง ใช้เลือก popup ตอนออกจากช่อง
            problems() {
                if (this.readOnly) return [];

                // เว้นเลขปัจจุบันว่าง = ข้ามมิเตอร์นี้ ไม่บังคับวันที่/รูป/เลขอื่นของแถว (ตรงกับ checkInput())
                // ยกเว้นแถวที่บันทึกไว้แล้ว: ล้างเลขทิ้งไม่ได้ ต้องใช้ปุ่มลบข้อมูลงวด
                if (this.v.current === '') {
                    if (this.savedCurrent === null) return [];
                    return [{
                        row: this, field: 'current', fields: ['current'], el: this.input('current'), kind: null,
                        title: 'ล้างเลขที่บันทึกไว้ไม่ได้',
                        text: this.label + ': งวดนี้บันทึกเลข ' + this.pad(this.savedCurrent) + ' ไว้แล้ว กรอกเลขปัจจุบัน หรือใช้ปุ่มลบข้อมูลงวดนี้',
                        list: this.label + ': ล้างเลขปัจจุบันที่บันทึกไว้ (' + this.pad(this.savedCurrent) + ') ไม่ได้',
                    }];
                }

                const out = [];
                const seen = [];
                const label = this.label;
                const digits = this.digits;
                const example = this.example;
                const add = (field, fields, title, text, list, kind) => {
                    if (seen.indexOf(field) !== -1) return;
                    seen.push(field);
                    out.push({ row: this, field: field, fields: fields, el: this.focusTarget(field), title: title, text: text, list: list, kind: kind || null });
                };

                ['previous', 'current', 'oldFinal', 'newStart'].forEach((f) => {
                    if (! this.present(f)) return;
                    const val = this.v[f];
                    if (val === '') {
                        add(f, [f], 'ยังไม่ได้กรอก' + METER_WHAT[f], label + ': กรอก ' + digits + ' หลักตามหน้าปัด เช่น ' + example, label + ': ยังไม่ได้กรอก' + METER_WHAT[f]);
                    } else if (val.length !== digits) {
                        add(f, [f], 'เลขไม่ครบ ' + digits + ' หลัก', label + ' ' + METER_WHAT[f] + ' กรอกแล้ว ' + val.length + ' หลัก ต้องมี ' + digits + ' หลัก เช่น ' + example, label + ': ' + METER_WHAT[f] + 'ต้องมี ' + digits + ' หลัก');
                    }
                });

                if (this.lockedPrevious !== null && this.lockedPrevious > this.max) {
                    add('current', ['current'], 'เลขเดือนก่อนเกิน ' + digits + ' หลัก', label + ': เลขเดือนก่อน ' + this.lockedPrevious + ' กรอกผิด แก้งวดเดือนก่อนให้ถูกก่อน', label + ': เลขเดือนก่อน ' + this.lockedPrevious + ' เกิน ' + digits + ' หลัก');
                }

                const previous = this.previousValue();
                const current = this.numberOf('current');
                if (this.changed) {
                    const oldFinal = this.numberOf('oldFinal');
                    const newStart = this.numberOf('newStart');
                    if (oldFinal !== null && previous !== null && oldFinal < previous) {
                        add('oldFinal', ['oldFinal', 'previous'], 'เลขสุดท้ายน้อยกว่าเดือนก่อน', label + ': เลขสุดท้ายก่อนเริ่มนับใหม่ ' + this.pad(oldFinal) + ' ต้องไม่น้อยกว่าเดือนก่อน ' + this.pad(previous), label + ': เลขสุดท้ายก่อนเริ่มนับใหม่ ' + this.pad(oldFinal) + ' น้อยกว่าเดือนก่อน ' + this.pad(previous));
                    }
                    if (current !== null && newStart !== null && current < newStart) {
                        add('current', ['current', 'newStart'], 'เลขปัจจุบันน้อยกว่าเลขเริ่มต้นใหม่', label + ': เลขปัจจุบัน ' + this.pad(current) + ' ต้องไม่น้อยกว่าเลขเริ่มต้นใหม่ ' + this.pad(newStart), label + ': เลขปัจจุบัน ' + this.pad(current) + ' น้อยกว่าเลขเริ่มต้นใหม่ ' + this.pad(newStart));
                    }
                } else if (current !== null && previous !== null && current < previous) {
                    add('current', ['current', 'previous'], 'เลขน้อยกว่าเดือนก่อน', label + ': เลขปัจจุบัน ' + this.pad(current) + ' น้อยกว่าเดือนก่อน ' + this.pad(previous), label + ': เลขปัจจุบัน ' + this.pad(current) + ' น้อยกว่าเดือนก่อน ' + this.pad(previous), 'less');
                }

                const dateEl = this.dateInput();
                if (dateEl && ! dateEl.disabled && ! dateEl.value) {
                    add('date', ['date'], 'ยังไม่ได้เลือกวันที่อ่านมิเตอร์', label + ': เลือกวันที่จดเลขจากหน้าปัด', label + ': ยังไม่ได้เลือกวันที่อ่านมิเตอร์');
                } else if (dateEl && ! dateEl.disabled && dateEl.dataset.prevDate && dateEl.value <= dateEl.dataset.prevDate) {
                    const prev = formatThaiDate(dateEl.dataset.prevDate);
                    add('date', ['date'], 'วันที่อ่านไม่ถูกต้อง', label + ': ต้องหลังวันที่จดเดือนก่อน (' + prev + ')', label + ': วันที่อ่านต้องหลัง ' + prev);
                }

                // รูปหน้าปัดบังคับเมื่อกรอกเลขปัจจุบันแล้ว (ตรงกับ checkInput()) - รูปที่บันทึกไว้แล้วนับว่ามี
                if (this.photos.main.present && ! this.hasPhoto('main')) {
                    add('photo', ['photo'], 'ยังไม่ได้แนบรูปมิเตอร์', label + ': กรอกเลขปัจจุบันแล้ว ต้องแนบรูปหน้าปัดที่เห็นเลขนี้', label + ': ยังไม่ได้แนบรูปมิเตอร์');
                }

                // งวดถัดไปออกใบแจ้งหนี้แล้ว: ห้ามเปลี่ยนเลขปัจจุบัน (บันทึกซ้ำด้วยเลขเดิมได้)
                if (this.next && this.next.locked && current !== null && current !== this.next.previous && current !== this.savedCurrent) {
                    add('current', ['current'], 'งวดถัดไปออกใบแจ้งหนี้แล้ว', label + ': งวด ' + this.next.period + ' เริ่มจาก ' + this.pad(this.next.previous) + ' เลขปัจจุบันต้องเป็น ' + this.pad(this.next.previous), label + ': เลขปัจจุบันต้องเป็น ' + this.pad(this.next.previous) + ' (งวด ' + this.next.period + ' ออกใบแจ้งหนี้แล้ว)');
                }

                return out;
            },

            // เรื่องน่าสงสัย: บันทึกได้ แต่ถามก่อน
            warnings() {
                // เว้นเลขปัจจุบันว่าง = ข้ามมิเตอร์นี้เงียบๆ ไม่ถามซ้ำ (ไม่บังคับกรอก - ผู้ใช้ขอ 2026-10-15)
                if (this.readOnly || this.isSkipped()) return [];
                const out = [];
                const previous = this.previousValue();
                const current = this.numberOf('current');
                if (this.changed && current !== null && previous !== null && current >= previous) {
                    out.push({ el: this.changedBtn(), list: this.label + ': เปิด "มิเตอร์เริ่มนับใหม่" ทั้งที่เลขไม่น้อยกว่าเดือนก่อน จะคิด ' + meterFmtUnits(this.liveUnits) + ' หน่วย' });
                }
                if (this.next && ! this.next.locked && current !== null && current !== this.next.previous) {
                    out.push({ el: this.input('current'), list: this.label + ': งวด ' + this.next.period + ' เริ่มจาก ' + this.pad(this.next.previous) + ' ไม่ตรงกับเลขนี้ หลังบันทึกระบบจะพาไปบันทึกงวดนั้นซ้ำ' });
                }
                if (this.photos.restart.present && this.photos.restart.saved && ! this.changed) {
                    out.push({ el: this.changedBtn(), list: this.label + ': ปิด "มิเตอร์เริ่มนับใหม่" แล้ว รูปเลขสุดท้ายที่บันทึกไว้จะถูกลบ' });
                }
                return out;
            },

            // ออกจากช่องแล้วผิด -> popup ทันที (เฉพาะเรื่องของช่องนี้) - เลขน้อยกว่าเดือนก่อนให้เลือกแก้เลข/เปิดเริ่มนับใหม่ได้เลย
            checkLater(f) {
                meterPendingChecks.push(setTimeout(() => {
                    if (Date.now() - meterSubmitIntentAt < 1500 || MeterDialog.isOpen()) return;
                    this.checkField(f);
                }, 150));
            },
            checkField(f) {
                if (f !== 'date' && this.v[f] === '') return;
                const found = this.problems().filter(p => p.fields.indexOf(f) !== -1 && (p.field === f || this.filled(p.field)));
                if (! found.length) return;
                const p = found[0];
                this.markInvalid(p.field);

                const changedBtn = this.changedBtn();
                if (p.kind === 'less' && changedBtn && ! changedBtn.disabled) {
                    MeterDialog.open({
                        icon: 'warning',
                        title: p.title,
                        text: p.text,
                        hint: 'ถ้ามิเตอร์วนกลับเป็น 0 หรือเปลี่ยนตัวใหม่ เลือก "มิเตอร์เริ่มนับใหม่"',
                        okText: 'มิเตอร์เริ่มนับใหม่',
                        cancelText: 'แก้เลข',
                        focusCancel: true,
                    }).then((ok) => {
                        if (ok) {
                            this.invalid.current = false;
                            this.setChanged(true);
                            this.$nextTick(() => meterFocus(this.input('oldFinal')));
                        } else {
                            meterFocus(this.input('current'));
                        }
                    });
                    return;
                }

                MeterDialog.open({ icon: 'error', title: p.title, text: p.text, focus: p.el });
            },

        };
    }

    // เลือกข้อความในช่องเลขทั้งหมดทันทีที่โฟกัส - พิมพ์ทับได้เลยโดยไม่ต้องลบก่อน
    document.addEventListener('focus', function (e) {
        if (e.target.matches && e.target.matches('input[data-digit-input]:not([disabled])')) {
            e.target.select();
        }
    }, true);

    if (meterForm) {
        // Enter ในช่องกรอก = ไปช่องถัดไป (ไม่ส่งฟอร์มก่อนเวลา - และไม่ให้เบราว์เซอร์กดปุ่มแรกของฟอร์ม "ยืนยันข้อมูล" แทน)
        meterForm.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || e.isComposing) return;
            const el = e.target;
            if (el.tagName !== 'INPUT' || el.type === 'file') return;
            e.preventDefault();
            if (! el.matches('[data-digit-input]')) return;
            const order = Array.from(meterForm.querySelectorAll('input[data-digit-input]:not([disabled]), textarea:not([disabled])'))
                .filter(n => n.getClientRects().length > 0);
            const nextEl = order[order.indexOf(el) + 1];
            if (nextEl) {
                nextEl.focus();
            } else {
                el.blur();
                Array.from(meterForm.querySelectorAll('[data-main-submit]')).find(b => b.getClientRects().length > 0)?.focus();
            }
        });

        meterForm.addEventListener('input', function (e) {
            if (e.target.id === 'remark') meterMarkDirty();
        });

        // ---- กดบันทึก: ตรวจทุกแถว -> ผิด = popup รายการ / น่าสงสัย = ถามก่อน / ผ่าน = ส่ง + กันกดซ้ำ ----
        meterForm.querySelectorAll('[type="submit"]').forEach(function (btn) {
            btn.addEventListener('pointerdown', function () { meterSubmitIntentAt = Date.now(); });
            btn.addEventListener('click', function () { meterLastSubmitter = btn; meterSubmitIntentAt = Date.now(); });
        });

        meterForm.addEventListener('submit', function (e) {
            const submitter = e.submitter || meterLastSubmitter;
            // ปุ่ม "ยืนยันข้อมูล" ส่งไป route อื่น (formaction) ไม่ได้ใช้เลขในฟอร์ม
            if (submitter && submitter.hasAttribute('formaction')) {
                meterLeaving = true;
                return;
            }
            e.preventDefault();
            meterPendingChecks.splice(0).forEach(clearTimeout);

            if (meterRows.some(r => r.busy())) {
                MeterDialog.open({ icon: 'info', title: 'กำลังเตรียมรูป', text: 'รอสักครู่ให้รูปเตรียมเสร็จ แล้วกดบันทึกอีกครั้ง' });
                return;
            }

            // เลขปัจจุบันไม่บังคับ แม้ว่างทุกมิเตอร์ (ผู้ใช้ขอ 2026-10-15) - เซิร์ฟเวอร์ไม่บันทึกอะไรแล้วพาไปหน้าถัดไปเอง
            const hard = meterRows.reduce((all, r) => all.concat(r.problems()), []).sort(meterByScreenOrder);
            if (hard.length) {
                hard.forEach(p => p.row.markInvalid(p.field));
                meterShowProblems(hard);
                return;
            }

            const soft = meterRows.reduce((all, r) => all.concat(r.warnings()), []);
            if (! soft.length) {
                meterSend(submitter);
                return;
            }

            MeterDialog.open({
                icon: 'warning',
                title: 'ตรวจอีกครั้งก่อนบันทึก',
                list: soft.map(w => w.list),
                okText: 'บันทึกเลย',
                cancelText: 'กลับไปแก้',
                focusCancel: true,
            }).then(function (ok) {
                if (ok) {
                    meterSend(submitter);
                } else {
                    meterFocus(soft[0].el);
                }
            });
        });
    }

    function meterSend(submitter) {
        // form.submit() ไม่ส่งค่าของปุ่มที่กด - ปุ่ม "บันทึก แล้วไปทรัพย์ถัดไป" (name=next) ต้องใส่เป็น hidden เอง
        if (submitter && submitter.name) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = submitter.name;
            hidden.value = submitter.value;
            meterForm.appendChild(hidden);
        }
        meterLeaving = true;
        meterForm.querySelectorAll('[type="submit"]').forEach(function (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-60', 'cursor-wait');
        });
        meterForm.querySelectorAll('[data-save-label]').forEach(function (el) {
            el.textContent = 'กำลังบันทึก...';
        });
        meterDropBackGuard(function () { meterForm.submit(); });
    }

    // กลับมาหน้านี้ด้วยปุ่มย้อนกลับ (bfcache) หลังส่งฟอร์มแล้ว - ปุ่มยังถูกล็อกอยู่ โหลดหน้าใหม่ให้ข้อมูลตรงกับที่บันทึก
    window.addEventListener('pageshow', function (e) {
        if (e.persisted && meterLeaving) window.location.reload();
    });

    // ---- ออกจากหน้าโดยยังไม่บันทึก: ถามด้วย popup ของหน้านี้ (MeterDialog) เสมอ ----
    // ไม่ใช้ beforeunload เพราะเบราว์เซอร์จะขึ้นกล่อง "Leave site?" ของตัวเองซึ่งตกแต่งไม่ได้ (ผู้ใช้ไม่ต้องการ 2026-10-15)
    // และหน้าแอดมินก็ไม่ใช้ - รีเฟรช/ปิดแท็บจึงออกได้ทันทีเหมือนแอดมิน ส่วนลิงก์ในหน้าและปุ่มย้อนกลับถามก่อน
    function meterConfirmLeave() {
        return MeterDialog.open({
            icon: 'warning',
            title: 'ยังไม่ได้บันทึก',
            text: 'เลขมิเตอร์ที่กรอกไว้จะหาย ถ้าออกจากหน้านี้ตอนนี้',
            okText: 'ออกโดยไม่บันทึก',
            okVariant: 'danger',
            cancelText: 'อยู่ต่อ',
            focusCancel: true,
        });
    }

    // ปุ่มย้อนกลับของเบราว์เซอร์/มือถือ: พอเริ่มแก้ฟอร์ม ใส่ history entry (URL เดิม) กันไว้ 1 ชั้น
    // กดย้อนกลับ = entry นั้นถูกเอาออก (popstate) -> ถามด้วย popup: ออก = ย้อนกลับต่อจริง / อยู่ต่อ = ใส่ entry กันไว้ใหม่
    let meterBackGuard = false;      // มี entry กันไว้อยู่
    let meterAfterGuardPop = null;   // งานที่ต้องทำต่อหลังเอา entry กันออกเอง (ส่งฟอร์ม/ไปลิงก์) - ไม่ให้เหลือ entry ซ้ำในประวัติ

    function meterMarkDirty() {
        meterDirty = true;
        if (meterBackGuard || meterLeaving) return;
        meterBackGuard = true;
        history.pushState({ meterBackGuard: true }, '', location.href);
    }

    function meterDropBackGuard(then) {
        if (! meterBackGuard) { then(); return; }
        meterBackGuard = false;
        meterAfterGuardPop = then;
        history.back();
    }

    window.addEventListener('popstate', function () {
        if (meterAfterGuardPop) {
            const then = meterAfterGuardPop;
            meterAfterGuardPop = null;
            then();
            return;
        }
        if (! meterBackGuard) return;
        meterBackGuard = false;
        if (! meterDirty || meterLeaving) { history.back(); return; }
        meterConfirmLeave().then(function (ok) {
            if (ok) {
                meterLeaving = true;
                history.back();
            } else {
                meterBackGuard = true;
                history.pushState({ meterBackGuard: true }, '', location.href);
            }
        });
    });

    document.addEventListener('click', function (e) {
        if (! meterDirty || meterLeaving || e.defaultPrevented) return;
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const link = e.target.closest('a[href]');
        if (! link || link.target === '_blank' || link.hasAttribute('download')) return;
        const href = link.getAttribute('href') || '';
        if (href === '' || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        e.preventDefault();
        meterConfirmLeave().then(function (ok) {
            if (! ok) return;
            meterLeaving = true;
            meterDropBackGuard(function () { window.location.href = link.href; });
        });
    });
    // ฟอร์มอื่นในหน้า (ลบข้อมูลงวด, ออกจากระบบ) ยืนยันกันไปแล้วในกล่องของมันเอง - ไม่ต้องถามซ้ำ
    document.addEventListener('submit', function (e) {
        if (e.target !== meterForm) meterLeaving = true;
    }, true);

    // ---- ผลจากเซิร์ฟเวอร์ตอนโหลดหน้า: error ทุกช่อง = popup รายการเดียว (เรียงตามจอ), งานต่อเนื่อง = popup แจ้ง ----
    document.addEventListener('alpine:initialized', function () {
        const problems = Object.keys(METER_SERVER_ERRORS).map(function (key) {
            const parts = key.split('.');
            const name = parts[0] + parts.slice(1).map(p => '[' + p + ']').join('');
            let el = meterForm ? meterForm.querySelector('[name="' + name + '"]') : null;
            if (el && el.matches('input[name$="[reading_date]"]')) {
                el = el.closest('[data-date-field]')?.querySelector('[data-toggle]') || null;
                el && el.classList.add('ring-2', 'ring-red-400');
            } else if (el && el.type === 'file') {
                el = el.closest('label');
            }
            const message = METER_SERVER_ERRORS[key][0];
            return { el: el && ! el.disabled ? el : null, title: 'บันทึกไม่ได้', text: message, list: message };
        });

        if (problems.length) {
            // บันทึกไม่สำเร็จ = เบราว์เซอร์ไม่เก็บรูปที่เลือกไว้ - บอกให้เลือกใหม่ (แถวที่ยังไม่มีรูปที่บันทึกไว้)
            meterRows.forEach(function (r) {
                if (r.readOnly || ! r.photos.main.present || r.hasPhoto('main') || r.v.current === '') return;
                if (METER_SERVER_ERRORS['readings.' + r.meterId + '.image']) return;
                const text = r.label + ': เลือกรูปมิเตอร์ใหม่ (บันทึกไม่สำเร็จ ระบบจึงไม่ได้เก็บรูปที่เลือกไว้)';
                r.markInvalid('photo');
                problems.push({ el: r.photoPick('main'), title: 'เลือกรูปมิเตอร์ใหม่', text: text, list: text });
            });
            meterShowProblems(problems.sort(meterByScreenOrder));
            return;
        }
        if (METER_NOTICE) {
            MeterDialog.open({ icon: 'info', title: METER_NOTICE.title, text: METER_NOTICE.text });
        }
    });
</script>
@endpush
