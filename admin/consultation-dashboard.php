<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

renderAdminHead('Consultation Dashboard', 'consultation-dashboard');
?>

<div class="page-banner">
    <h1>Consultation Overview</h1>
    <p>Monitor active consultations, feedback trends, and engagement metrics at a glance.</p>
</div>

<div class="stat-cards">
    <div class="stat-card">
        <div class="label">Total Consultations</div>
        <div class="value dark">2</div>
    </div>
    <div class="stat-card">
        <div class="label">Pending Review</div>
        <div class="value orange">1</div>
    </div>
    <div class="stat-card">
        <div class="label">Total Feedback</div>
        <div class="value blue">2</div>
    </div>
    <div class="stat-card">
        <div class="label">Documents</div>
        <div class="value green">0</div>
    </div>
</div>

<div class="admin-card mb-6">
    <div class="admin-card-header flex justify-between items-center">
        <span><i class="fa-solid fa-calendar-days mr-2 text-red-600"></i> Consultation Calendar</span>
        <span class="text-sm font-normal text-slate-500">July 2026</span>
    </div>
    <div class="admin-card-body">
        <div class="calendar-grid mb-2">
            <?php
            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($days as $day) {
                echo '<div class="calendar-day-header">' . $day . '</div>';
            }
            // July 2026 starts on Wednesday (offset 3)
            for ($i = 0; $i < 3; $i++) {
                echo '<div class="calendar-day other-month">' . (28 + $i) . '</div>';
            }
            $eventDays = [5, 12, 17, 22];
            for ($d = 1; $d <= 31; $d++) {
                $classes = 'calendar-day';
                if ($d === 18) $classes .= ' today';
                elseif (in_array($d, $eventDays, true)) $classes .= ' has-event';
                echo '<div class="' . $classes . '">' . $d . '</div>';
            }
            for ($i = 1; $i <= 5; $i++) {
                echo '<div class="calendar-day other-month">' . $i . '</div>';
            }
            ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="admin-card">
        <div class="admin-card-header">Feedback Sentiment</div>
        <div class="admin-card-body">
            <div class="sentiment-bar">
                <div class="positive"></div>
                <div class="neutral"></div>
                <div class="negative"></div>
            </div>
            <div class="flex justify-between text-xs text-slate-500 mt-2">
                <span><span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>Positive 60%</span>
                <span><span class="inline-block w-2 h-2 rounded-full bg-slate-400 mr-1"></span>Neutral 20%</span>
                <span><span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span>Negative 20%</span>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">Survey Response Summary</div>
        <div class="admin-card-body">
            <div class="survey-bar">
                <span class="text-xs text-slate-600 w-24">Parks Survey</span>
                <div class="bar-track"><div class="bar-fill" style="width:75%"></div></div>
                <span class="text-xs font-bold text-slate-700">75%</span>
            </div>
            <div class="survey-bar">
                <span class="text-xs text-slate-600 w-24">Safety Survey</span>
                <div class="bar-track"><div class="bar-fill" style="width:45%"></div></div>
                <span class="text-xs font-bold text-slate-700">45%</span>
            </div>
            <div class="survey-bar">
                <span class="text-xs text-slate-600 w-24">Health Survey</span>
                <div class="bar-track"><div class="bar-fill" style="width:30%"></div></div>
                <span class="text-xs font-bold text-slate-700">30%</span>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">Total Consultation</div>
        <div class="admin-card-body text-center">
            <div class="donut-chart"></div>
            <div class="flex justify-center gap-6 mt-4 text-xs">
                <span><span class="inline-block w-2 h-2 rounded-full bg-blue-500 mr-1"></span>Active 1</span>
                <span><span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>Closed 1</span>
            </div>
        </div>
    </div>
</div>

<?php renderAdminFoot(); ?>
