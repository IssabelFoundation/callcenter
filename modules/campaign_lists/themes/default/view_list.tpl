<link href="modules/campaign_lists/themes/default/custom_css/onepcssgrid.css" rel="stylesheet" type="text/css" />
<script src="modules/campaign_lists/themes/default/custom_js/Chart.bundle.min.js"></script>

<table style="" width="99%" border="0" cellspacing="0" cellpadding="0" align="center">
{if !$FRAMEWORK_TIENE_TITULO_MODULO}
<tr class="moduleTitle">
  <td class="moduleTitle" valign="middle">&nbsp;&nbsp;<img src="{$icon}" border="0" align="absmiddle" />&nbsp;&nbsp;{$title}</td>
</tr>
{/if}
<tr>
  <td style="font-weight: bold;">
    <div class="onepcssgrid-1000">

      <div class="onerow" style="">
        
        <div class="col8">
            <div style="margin-bottom: 21px;background-color: #ffffff;border-radius: 4px;-webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);border-color: #ecf0f1;">
              <table>
                <tbody>
                  <tr>
                    <td>ID</td>
                    <td>&nbsp;</td>
                    <td>{$id_list}</td>
                  </tr>
                  <tr>
                    <td>Nombre</td>
                    <td>&nbsp;</td>
                    <td>{$name_list}</td>
                  </tr>
                  <tr>
                    <td>Archivo</td>
                    <td>&nbsp;</td>
                    <td>{$file_list}</td>
                  </tr>
                  <tr>
                    <td>Fecha Creación</td>
                    <td>&nbsp;</td>
                    <td>{$date_list}</td>
                  </tr>
                  <tr>
                    <td>Estado</td>
                    <td>&nbsp;</td>
                    <td>{$status_list}</td>
                  </tr>
                  <tr>
                    <td>Total de registros</td>
                    <td>&nbsp;</td>
                    <td>{$total_calls}</td>
                  </tr>
                </tbody>
              </table>
            </div>
        </div>

        <div class="col4 last">
          <canvas id="myDoughnut"></canvas>
          <script>
          var ctx = document.getElementById("myDoughnut").getContext('2d');
          //ctx.height = 200;
          var myChart = new Chart(ctx, {
              type: 'doughnut',
              "data": { 
                "labels": ["{$labelDetailBar.sent_calls}", "{$labelDetailBar.pending_calls}"], 
                "datasets": [{
                  "label": "Progreso", 
                  "data": [{$sent_calls}, {$pending_calls}], 
                  "backgroundColor": ["rgba(82,43,118,0.8)","rgba(82,43,118,0.4)"] 
                }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
              }
          });
          </script>
        </div>
      </div>
    </div>
    
    <div class="onerow" style="">
        
        <div class="col12">
          <canvas id="myChart"></canvas>
          <script>
          var ctx = document.getElementById("myChart").getContext('2d');
          ctx.height = 150;
          var myChart = new Chart(ctx, {
              type: 'line',
              data: {
                  labels: ['{$labelWeek.monday}', '{$labelWeek.tuesday}', '{$labelWeek.wednesday}', '{$labelWeek.thursday}', '{$labelWeek.friday}', '{$labelWeek.saturday}', '{$labelWeek.sunday}'],
                  datasets: [{
                      label: '{$label_week}',
                      data: [{$dataWeek.monday}, {$dataWeek.tuesday}, {$dataWeek.wednesday}, {$dataWeek.thursday}, {$dataWeek.friday}, {$dataWeek.saturday}, {$dataWeek.sunday}],
                      fill: false,
                      borderColor: "rgb(74,39,106)",
                      
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  scales: {
                      yAxes: [{
                          ticks: {
                              beginAtZero:true
                          }
                      }]
                  }
              }
          });
          </script>
        </div>
      </div>
      <div class="onerow" style="">
        <p style="text-align: center;">Detalle de llamadas</p>
        <div class="col12">
          <canvas id="myChartBar"></canvas>
          <script>
          var ctx = document.getElementById("myChartBar").getContext('2d');
          ctx.height = 200;
          var myChart = new Chart(ctx, {
              type: 'bar',
              data: {
                  labels: ["{$labelDetailBar.pending_calls}", "{$labelDetailBar.paused_calls}", "{$labelDetailBar.sent_calls}", "{$labelDetailBar.answered_calls}", "{$labelDetailBar.no_answer_calls}", "{$labelDetailBar.failed_calls}", "{$labelDetailBar.short_calls}", "{$labelDetailBar.abandoned_calls}"],
                  datasets: [{
                      label: '{$label_bar_chart}',
                      data: [{$pending_calls}, {$paused_calls}, {$sent_calls}, {$answered_calls}, {$no_answer_calls}, {$failed_calls}, {$short_calls}, {$abandoned_calls}],
                      backgroundColor : "rgba(82,43,118,0.5)",
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  scales: {
                      yAxes: [{
                          ticks: {
                              beginAtZero:true
                          }
                      }]
                  }
              }
          });
          </script>
        </div>
      </div>

  </td>
</tr>

</table>
