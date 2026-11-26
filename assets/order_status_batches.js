(() => {
  const panel = document.getElementById('wbPanel'); if (!panel) return;
  const byId = id => document.getElementById(id);
  let state, busy = false, stop = false;
  const message = text => { byId('wbMessage').textContent = text; };
  function render(next) {
    state = next;
    byId('wbState').textContent = state.state;
    byId('wbProgress').value = state.processed;
    byId('wbCount').textContent = `${state.processed.toLocaleString()} of ${state.total.toLocaleString()} orders checked`;
    byId('wbCounts').replaceChildren();
    Object.entries(state.counts).forEach(([label, count]) => {
      const item = document.createElement('div'), value = document.createElement('strong'), title = document.createElement('span');
      value.textContent = count.toLocaleString(); title.textContent = label; item.append(value, title); byId('wbCounts').append(item);
    });
    byId('wbDone').hidden = !state.complete;
    byId('wbResume').hidden = state.complete || (state.state === 'Running' && !stop);
    byId('wbPause').hidden = state.complete || state.state === 'Paused' || stop;
    if (state.complete) message('Processing complete. Open View Results for updated orders and any remaining Needs Checking items.');
    else if (state.state === 'Paused') message('Processing paused. Completed batches are saved. Select Resume Processing to continue.');
  }
  async function request(mode) {
    const data = new URLSearchParams({ upload_id: panel.dataset.upload, token: panel.dataset.token, csrf: panel.dataset.csrf, cursor: state?.processed ?? -1, mode });
    const response = await fetch('order_status_batch_action.php', { method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store' });
    let result; try { result = await response.json(); } catch { throw new Error('The connection was interrupted. Reopen this upload or select Resume to check saved progress.'); }
    if (!response.ok || !result.ok) throw new Error(result.message || 'Processing stopped. Select Resume to check saved progress.');
    render(result.progress); return state;
  }
  function failed(error) { stop = true; message(error.message + ' Completed batches remain saved.'); byId('wbResume').hidden = false; byId('wbPause').hidden = true; }
  async function run() {
    if (busy) return; busy = true;
    try {
      while (!stop && state.state === 'Running' && !state.complete) { await request('work'); await new Promise(resolve => setTimeout(resolve, 120)); }
      if (stop && !state.complete) await request('pause');
    } catch (error) { failed(error); } finally { busy = false; }
  }
  byId('wbPause').addEventListener('click', () => { stop = true; message('Pausing after the current batch is saved…'); });
  byId('wbResume').addEventListener('click', async () => {
    if (busy) return; busy = true; stop = false;
    try { await request('resume'); message('Continuing from saved progress…'); } catch (error) { failed(error); } finally { busy = false; }
    if (!stop) run();
  });
  request('state').then(() => run()).catch(failed);
})();
