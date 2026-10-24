(function(){
 document.querySelectorAll('[data-report-filter-form]').forEach(function(form){
  var select=form.querySelector('[name="report"]');if(!select)return;
  function update(){var snapshot=['stocks','restricted','platforms'].includes(select.value),master=select.value==='product_master';
   form.querySelectorAll('[data-report-period]').forEach(function(label){label.hidden=snapshot||master;label.querySelectorAll('input').forEach(function(input){input.disabled=snapshot||master;});});
   form.querySelectorAll('[data-report-asof]').forEach(function(label){label.hidden=!snapshot;label.querySelectorAll('input').forEach(function(input){input.disabled=!snapshot;});});
  }select.addEventListener('change',update);update();
 });
})();
