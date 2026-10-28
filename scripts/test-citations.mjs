import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {Cite,plugins} from '@citation-js/core';
import '@citation-js/plugin-csl';
import '@citation-js/plugin-bibtex';
import '@citation-js/plugin-ris';
for(const key of ['apa','chicago','ieee']) plugins.config.get('@csl').styles.add(key,await readFile(new URL('../public/citation-styles/'+key+'.csl',import.meta.url),'utf8'));
plugins.config.get('@csl').locales.add('es-ES',await readFile(new URL('../public/citation-styles/locales-es-ES.xml',import.meta.url),'utf8'));
const cite=new Cite({id:'sample',type:'article-journal',title:'Estudio académico de prueba',author:[{literal:'Ana Pérez'}],issued:{'date-parts':[[2025]]},'container-title':'Revista de prueba',volume:'2',issue:'1',page:'10-20',DOI:'10.1234/example'});
for(const template of ['apa','chicago','ieee','vancouver']){const text=cite.format('bibliography',{format:'text',template,lang:'es-ES'});assert.match(text,/2025/);assert.match(text,/prueba/);console.log(template+': correcto');}
for(const format of ['bibtex','ris'])assert.ok(cite.format(format).length>20);
console.log('Seis estilos/formato de cita comprobados.');
