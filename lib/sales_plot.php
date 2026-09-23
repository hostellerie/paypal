<?php

function PAYPAL_plot($period = '12m')
{
    global $_CONF, $_PAY_CONF, $_TABLES, $LANG_PAYPAL_1;

    $periods = array(
        '1m' => array('months' => 1, 'label' => '1 ' . $LANG_PAYPAL_1['sales_month']),
        '3m' => array('months' => 3, 'label' => '3 ' . $LANG_PAYPAL_1['sales_months']),
        '6m' => array('months' => 6, 'label' => '6 ' . $LANG_PAYPAL_1['sales_months']),
        '9m' => array('months' => 9, 'label' => '9 ' . $LANG_PAYPAL_1['sales_months']),
        '12m' => array('months' => 12, 'label' => '12 ' . $LANG_PAYPAL_1['sales_months']),
        '2y' => array('months' => 24, 'label' => '2 ' . $LANG_PAYPAL_1['sales_years']),
        '3y' => array('months' => 36, 'label' => '3 ' . $LANG_PAYPAL_1['sales_years']),
        '5y' => array('months' => 60, 'label' => '5 ' . $LANG_PAYPAL_1['sales_years']),
        '10y' => array('months' => 120, 'label' => '10 ' . $LANG_PAYPAL_1['sales_years']),
        '20y' => array('months' => 240, 'label' => '20 ' . $LANG_PAYPAL_1['sales_years']),
    );

    if (!isset($periods[$period])) {
        $period = '12m';
    }

    $months = $periods[$period]['months'];
    $aggregateByYear = ($months >= 60);
    $plots = array();
    $totalPeriod = 0.0;

    if ($aggregateByYear) {
        $startTimestamp = strtotime('-' . ($months - 1) . ' months', strtotime(date('Y-m-01')));
        $startYear = (int) date('Y', $startTimestamp);
        $endYear = (int) date('Y');

        for ($year = $startYear; $year <= $endYear; ++$year) {
            $plots[(string) $year] = 0.0;
        }
    } else {
        for ($i = $months - 1; $i >= 0; --$i) {
            $timestamp = strtotime('-' . $i . ' months', strtotime(date('Y-m-01')));
            $plots[date('Y-m', $timestamp)] = 0.0;
        }
    }

    $startDate = date(
        'Y-m-01',
        strtotime('-' . ($months - 1) . ' months', strtotime(date('Y-m-01')))
    );
    $safeStartDate = DB_escapeString($startDate);

    $sql = "SELECT p.purchase_date, i.ipn_data
        FROM {$_TABLES['paypal_purchases']} AS p
        LEFT JOIN {$_TABLES['paypal_ipnlog']} AS i
            ON p.txn_id = i.txn_id
        WHERE p.status = 'complete'
          AND p.purchase_date >= '{$safeStartDate}'
        ORDER BY p.purchase_date";

    $result = DB_query($sql);

    while ($A = DB_fetchArray($result)) {
        $serialized = isset($A['ipn_data']) ? $A['ipn_data'] : '';
        $normalized = preg_replace_callback(
            '!s:(\\d+):"(.*?)";!s',
            function ($matches) {
                return 's:' . strlen($matches[2]) . ':"' . $matches[2] . '";';
            },
            $serialized
        );

        $ipn = @unserialize($normalized);
        $gross = is_array($ipn) && isset($ipn['mc_gross']) && is_numeric($ipn['mc_gross'])
            ? (float) $ipn['mc_gross']
            : 0.0;

        $timestamp = strtotime($A['purchase_date']);
        if ($timestamp === false) {
            continue;
        }

        $key = $aggregateByYear ? date('Y', $timestamp) : date('Y-m', $timestamp);

        if (isset($plots[$key])) {
            $plots[$key] += $gross;
            $totalPeriod += $gross;
        }
    }

    $max = !empty($plots) ? max($plots) : 0.0;
    $chartWidth = 960;
    $chartHeight = 280;
    $paddingLeft = 60;
    $paddingRight = 20;
    $paddingTop = 20;
    $paddingBottom = 55;
    $plotWidth = $chartWidth - $paddingLeft - $paddingRight;
    $plotHeight = $chartHeight - $paddingTop - $paddingBottom;

    $count = count($plots);
    $points = array();
    $labels = '';
    $index = 0;

    // Keep labels readable when displaying 24 or 36 monthly data points.
    $labelEvery = 1;
    if (!$aggregateByYear && $count > 18) {
        $labelEvery = 3;
    } elseif ($aggregateByYear && $count > 12) {
        $labelEvery = 2;
    }

    foreach ($plots as $key => $amount) {
        $x = $paddingLeft;
        if ($count > 1) {
            $x += ($plotWidth / ($count - 1)) * $index;
        }

        $y = $max > 0
            ? $paddingTop + $plotHeight - (($amount / $max) * $plotHeight)
            : $paddingTop + $plotHeight;

        $points[] = round($x, 2) . ',' . round($y, 2);

        if (($index % $labelEvery) === 0 || $index === ($count - 1)) {
            $label = $aggregateByYear ? $key : $key;
            $labels .= '<text x="' . round($x, 2) . '" y="' . ($chartHeight - 20)
                . '" text-anchor="middle" class="paypal-sales-axis-label">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</text>';
        }

        ++$index;
    }

    $axisMaxLabel = number_format(
        $max,
        $_CONF['decimal_count'],
        $_CONF['decimal_separator'],
        $_CONF['thousand_separator']
    );

    $svg = '<div class="paypal-sales-chart" role="img" aria-label="'
        . htmlspecialchars($LANG_PAYPAL_1['sales_history'], ENT_QUOTES, 'UTF-8') . '">'
        . '<svg viewBox="0 0 ' . $chartWidth . ' ' . $chartHeight . '" preserveAspectRatio="none">'
        . '<line x1="' . $paddingLeft . '" y1="' . $paddingTop . '" x2="' . $paddingLeft
        . '" y2="' . ($paddingTop + $plotHeight) . '" class="paypal-sales-axis"></line>'
        . '<line x1="' . $paddingLeft . '" y1="' . ($paddingTop + $plotHeight) . '" x2="'
        . ($chartWidth - $paddingRight) . '" y2="' . ($paddingTop + $plotHeight)
        . '" class="paypal-sales-axis"></line>'
        . '<text x="5" y="' . ($paddingTop + 5) . '" class="paypal-sales-axis-value">'
        . htmlspecialchars($axisMaxLabel . ' ' . $_PAY_CONF['currency'], ENT_QUOTES, 'UTF-8')
        . '</text>'
        . '<text x="20" y="' . ($paddingTop + $plotHeight) . '" class="paypal-sales-axis-value">0</text>'
        . '<polyline points="' . implode(' ', $points) . '" class="paypal-sales-line"></polyline>'
        . $labels
        . '</svg>'
        . '</div>';

    $selector = '<form class="paypal-sales-period" method="get" action="">'
        . '<label for="paypal-sales-period">' . htmlspecialchars($LANG_PAYPAL_1['sales_period'], ENT_QUOTES, 'UTF-8') . '</label> '
        . '<select id="paypal-sales-period" name="period" onchange="this.form.submit()">';

    foreach ($periods as $value => $definition) {
        $selector .= '<option value="' . $value . '"'
            . ($value === $period ? ' selected' : '') . '>'
            . htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8')
            . '</option>';
    }

    $selector .= '</select><noscript> <button type="submit">'
        . htmlspecialchars($LANG_PAYPAL_1['apply'], ENT_QUOTES, 'UTF-8')
        . '</button></noscript></form>';

    $summary = '<p>'
        . $LANG_PAYPAL_1['period_stat'] . ' ' . $_PAY_CONF['currency'] . ' '
        . number_format(
            $totalPeriod,
            $_CONF['decimal_count'],
            $_CONF['decimal_separator'],
            $_CONF['thousand_separator']
        )
        . '</p>';

    return $selector . $summary . $svg;
}
