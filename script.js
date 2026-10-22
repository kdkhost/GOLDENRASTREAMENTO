/* CONFIGURAÇÃO DE HOMOLOGAÇÃO — substituir antes da publicação */
const CONFIG={whatsapp:"",leadEndpoint:"",trackingEnabled:false};
const qs=new URLSearchParams(location.search);
const campaign={utm_source:qs.get("utm_source")||"",utm_medium:qs.get("utm_medium")||"",utm_campaign:qs.get("utm_campaign")||"",utm_content:qs.get("utm_content")||"",utm_term:qs.get("utm_term")||"",gclid:qs.get("gclid")||"",fbclid:qs.get("fbclid")||""};
const visitId=(crypto.randomUUID?crypto.randomUUID():String(Date.now()));
window.dataLayer=window.dataLayer||[];
function event(name,details={}){const item={event:name,visit_id:visitId,...campaign,...details};if(CONFIG.trackingEnabled)window.dataLayer.push(item);console.info("[Prévia Golden] Evento local:",item)}
document.querySelectorAll(".track").forEach(el=>el.addEventListener("click",()=>event(el.dataset.event,{cta_id:el.dataset.cta})));
document.querySelectorAll(".plan").forEach(el=>el.addEventListener("click",()=>{document.getElementById("plan").value=el.dataset.plan;event("select_plan",{plan:el.dataset.plan,cta_id:"plan_"+el.dataset.plan})}));
function whatsapp(message,cta){event("whatsapp_click",{cta_id:cta});if(!CONFIG.whatsapp){alert("PRÉVIA: falta configurar o número oficial do WhatsApp no arquivo script.js. Nenhuma mensagem foi enviada.");return}window.open("https://wa.me/"+CONFIG.whatsapp+"?text="+encodeURIComponent(message)," _blank".trim(),"noopener")}
const defaultMessage="Olá! Conheci a Golden pelo site e gostaria de uma proposta. Referência: "+visitId.slice(0,8);
document.getElementById("directWa").addEventListener("click",e=>{e.preventDefault();whatsapp(defaultMessage,"contact_direct")});
document.getElementById("floatWa").addEventListener("click",e=>{e.preventDefault();whatsapp(defaultMessage,"floating")});
document.getElementById("leadForm").addEventListener("submit",async e=>{e.preventDefault();const form=e.currentTarget;if(!form.reportValidity())return;const data=Object.fromEntries(new FormData(form));data.visit_id=visitId;data.campaign=campaign;event("lead_form_ready",{plan:data.plan,profile:data.profile});const message=`Olá! Sou ${data.name}. Meu WhatsApp é ${data.phone}. Tenho interesse em ${data.plan} veículo(s). Perfil: ${data.profile}. Referência: ${visitId.slice(0,8)}.`;if(CONFIG.leadEndpoint){try{const r=await fetch(CONFIG.leadEndpoint,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});if(!r.ok)throw Error("HTTP "+r.status);event("generate_lead",{plan:data.plan});}catch(err){alert("Não foi possível registrar o cadastro. Você ainda pode iniciar o atendimento por WhatsApp.");}}else{alert("PRÉVIA: o formulário foi validado, mas nenhum dado foi enviado ou armazenado. Marcelo deverá configurar o endpoint seguro de leads.");}whatsapp(message,"lead_form")});
