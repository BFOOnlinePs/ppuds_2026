import Swal from 'sweetalert2'

window.Swal = Swal

// استيراد jQuery وتعريضه عالميًا
import $ from 'jquery';
window.$ = window.jQuery = $; [3]

// استيراد ApexCharts وتعريضه عالميًا
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

// استيراد SimpleBar (إذا كان القالب يستخدمه)
import SimpleBar from 'simplebar';
window.SimpleBar = SimpleBar;

// استيراد Preline (إذا كان القالب يستخدمه)

// استيراد ملفات JavaScript الرئيسية للقالب
import './dashboard.js';
import './sidebarmenu.js';

// قم بتهيئة أي سكريبتات للقالب تتطلب تهيئة عند تحميل الصفحة
// على سبيل المثال، إذا كان dashboard.js يحتوي على دالة init()
// document.addEventListener('DOMContentLoaded', () => {
//     if (typeof initDashboard!== 'undefined') {
//         initDashboard();
//     }
// });

