# SPDX-License-Identifier: GPL-3.0-or-later
# Sanitized historical copy; see ../browser-smoke.md before use.
import asyncio,json,urllib.request,pathlib,sys,os
import websockets
# Historical Phase-2 harness, not the current preview protocol.
OUT=pathlib.Path(os.environ['MSM_EVIDENCE_DIR'])
OUT.mkdir(parents=True, exist_ok=True)
BASE=os.environ.get('MSM_BASE_URL', 'http://127.0.0.1:8088').rstrip('/')
CDP=os.environ.get('MSM_CDP_URL', 'http://127.0.0.1:9223').rstrip('/')
async def main():
 pages=json.load(urllib.request.urlopen(CDP+'/json'))
 page=next(p for p in pages if p['type']=='page' and p['url'].startswith(BASE+'/'))
 async with websockets.connect(page['webSocketDebuggerUrl'],max_size=10000000) as ws:
  serial=0
  async def call(method,params=None):
   nonlocal serial
   serial+=1;req=serial;await ws.send(json.dumps({'id':req,'method':method,'params':params or {}}))
   while True:
    msg=json.loads(await ws.recv())
    if msg.get('id')==req:
     if 'error' in msg or 'exceptionDetails' in msg.get('result',{}):raise RuntimeError('CDP operation failed')
     return msg.get('result',{})
  async def js(code):
   r=await call('Runtime.evaluate',{'expression':code,'returnByValue':True,'awaitPromise':True});return r.get('result',{}).get('value')
  async def ready(path):
   for _ in range(40):
    await asyncio.sleep(.2)
    try:
     if await js('location.pathname === '+json.dumps(path)+' && document.readyState === "complete"'):return
    except RuntimeError:pass
   raise RuntimeError('Navigation timeout: '+path)
  async def nav(path):
   await call('Page.navigate',{'url':BASE+path});await ready(path.split('?')[0])
  results=[]
  def record(case,ok,detail):
   results.append({'case':case,'result':'PASS' if ok else 'FAIL','evidence':detail});print(case,results[-1]['result'])
   (OUT/'browser-smoke.json').write_text(json.dumps(results,indent=2))
   if not ok:raise RuntimeError('Browser assertion failed: '+case)
  await nav('/adherents/list.php')
  state=await js('''(()=>{const s=document.querySelector('select[name="massaction"]');return {present:!!s?.querySelector('option[value="memberSubscriptionMassactionStart"]'), core:s?Array.from(s.options).map(x=>x.value).filter(x=>x!=='memberSubscriptionMassactionStart'):[], fatal:/Fatal error|Parse error/.test(document.body.innerText)};})()''')
  record('ST-03/04',state['present'] and not state['fatal'],'Authenticated member list shows own action; no PHP error text.')
  (OUT/'browser-core-options.json').write_text(json.dumps(state['core']))
  await js('''(()=>{const boxes=Array.from(document.querySelectorAll('input[name="toselect[]"]'));if(boxes.length<2)throw Error('Need two accessible members');for(const b of boxes){if(b.checked)b.click();}for(const b of boxes.slice(0,2))b.click();sessionStorage.setItem('msmSmokeExpected',JSON.stringify(boxes.slice(0,2).map(x=>x.value)));const menu=document.querySelector('select[name="massaction"]');menu.value='memberSubscriptionMassactionStart';menu.dispatchEvent(new Event('change',{bubbles:true}));document.querySelector('[name="confirmmassaction"]').click();})()''')
  await ready('/custom/membersubscriptionmassaction/subscription.php')
  verify='''(()=>{const expected=JSON.parse(sessionStorage.getItem('msmSmokeExpected'));const shown=Array.from(document.querySelectorAll('table.noborder td')).map(x=>x.textContent.trim());return {matches:JSON.stringify(expected)===JSON.stringify(shown),count:shown.length,notice:/Technischer Integrationstest|Technical integration test/.test(document.body.innerText),fatal:/Fatal error|Parse error/.test(document.body.innerText)};})()'''
  state=await js(verify)
  record('ST-06/07/08',state['matches'] and state['count']==2 and state['notice'] and not state['fatal'],'Actual checkbox selection and Confirm redirect: exactly the two selected IDs displayed; no IDs retained in report.')
  await call('Page.reload');await ready('/custom/membersubscriptionmassaction/subscription.php')
  state=await js(verify);record('ST-14',state['matches'] and state['count']==2,'Reload preserves the same read-only selection.')
  await js('''document.querySelector('button[name="action"][value="cancel"]').click();''')
  await ready('/adherents/list.php')
  record('ST-13',await js('!location.search.includes("massaction") && !!document.querySelector("select[name=massaction]")'),'Cancel POST returns to the member list without a massaction parameter.')
  await nav('/custom/membersubscriptionmassaction/subscription.php')
  record('ST-10',await js('!document.querySelector("table.noborder") && /Diese Auswahl ist nicht verfügbar|This selection is unavailable/.test(document.body.innerText)'),'Authenticated direct GET without a valid run is denied.')
  for label,invalid in [('empty',None),('nonnumeric','invalid'),('negative','-1'),('nested',{'bad':'1'})]:
   await nav('/adherents/list.php')
   code='''(()=>{const boxes=Array.from(document.querySelectorAll('input[name="toselect[]"]'));for(const b of boxes)b.checked=false;const menu=document.querySelector('select[name="massaction"]');menu.value='memberSubscriptionMassactionStart';const button=document.querySelector('[name="confirmmassaction"]');const value=VALUE;if(value!==null){const input=document.createElement('input');input.type='hidden';input.name=typeof value==='object'?'toselect[0][bad]':'toselect[]';input.value=typeof value==='object'?'1':value;button.form.appendChild(input);}button.disabled=false;button.click();})()'''.replace('VALUE',json.dumps(invalid))
   await js(code);await asyncio.sleep(.5);await ready('/adherents/list.php')
   ok=await js('''/Die Auswahl ist ungültig|The selection is invalid|Zugriff verweigert|Access forbidden/.test(document.body.innerText) && location.pathname==='/adherents/list.php' ''')
   record('ST-09-'+label,ok,'Forged selection rejected on the real list POST; no wizard run opened.')
  await nav('/adherents/list.php')
  await js("sessionStorage.removeItem('msmSmokeExpected')")
  print('Browser smoke completed; business counts must be compared separately.')
asyncio.run(main())
