<?php
/** @var array $inst */
/** @var int $seatsUsed */
?>
<div class="grid grid-3">
  <div class="stat"><div class="label">Plan</div><div class="value" style="font-size:1.4rem"><?= e(ucfirst((string)$inst['subscription_tier'])) ?></div></div>
  <div class="stat"><div class="label">Licensed seats</div><div class="value"><?= (int)$inst['licensed_seats'] ?></div></div>
  <div class="stat"><div class="label">Seats in use</div><div class="value"><?= (int)$seatsUsed ?></div></div>
</div>
<div class="panel" style="margin-top:1rem">
  <h2>Tier comparison</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Tier</th><th>Price / prof / yr</th><th>Includes</th></tr></thead>
    <tbody>
      <tr><td>Starter</td><td>₹2,500</td><td>Course plan, Bloom, lessons, Q-bank, attendance, basic marks</td></tr>
      <tr><td>Professional</td><td>₹4,000</td><td>All Starter + PPT, assignments, HOD, AI review, formulas</td></tr>
      <tr><td>Enterprise</td><td>₹6,000</td><td>All Pro + NAAC builder, student portal, finance, API</td></tr>
    </tbody>
  </table></div>
</div>
