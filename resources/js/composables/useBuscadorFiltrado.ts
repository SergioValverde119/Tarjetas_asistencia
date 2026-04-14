import { ref, computed, watch } from 'vue';
import { debounce } from 'lodash';
import { router } from '@inertiajs/vue3';
import type { ElementoSeleccionable } from '@/types';

/**
 * Composable para gestionar la lógica de búsqueda y selección de elementos.
 * @param listaInicial La lista de elementos a filtrar localmente
 * @param rutaBusquedaServidor (Opcional) Si se requiere buscar en la base de datos vía Inertia
 */
export function useBuscadorFiltrado(
    listaInicial: any[] = [], 
    campoTitulo: string = 'titulo',
    rutaBusquedaServidor?: string
) {
    const terminoBusqueda = ref('');
    const estaCargando = ref(false);
    const mostrarSugerencias = ref(false);
    const elementoSeleccionado = ref<ElementoSeleccionable | null>(null);

    // Filtrado local para búsqueda instantánea
    const sugerenciasFiltradas = computed(() => {
        if (!terminoBusqueda.value || rutaBusquedaServidor) return listaInicial;
        
        const term = terminoBusqueda.value.toLowerCase();
        return listaInicial.filter(item => 
            String(item[campoTitulo]).toLowerCase().includes(term)
        );
    });

    // Lógica de búsqueda en servidor (si se proporciona una ruta)
    const buscarEnServidor = debounce((query: string) => {
        if (!rutaBusquedaServidor) return;
        
        estaCargando.ref = true;
        router.get(rutaBusquedaServidor, { search: query }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['employees'], // Esto se puede generalizar luego
            onFinish: () => estaCargando.value = false
        });
    }, 300);

    // Observar cambios en el texto para gatillar búsquedas o limpiar selección
    watch(terminoBusqueda, (nuevoValor) => {
        if (elementoSeleccionado.value && nuevoValor !== elementoSeleccionado.value.titulo) {
            elementoSeleccionado.value = null;
        }
        
        if (rutaBusquedaServidor) {
            buscarEnServidor(nuevoValor);
        }
        
        mostrarSugerencias.value = true;
    });

    const seleccionar = (item: any, transformador: (i: any) => ElementoSeleccionable) => {
        const estandar = transformador(item);
        elementoSeleccionado.value = estandar;
        terminoBusqueda.value = estandar.titulo;
        mostrarSugerencias.value = false;
    };

    const cerrarSugerencias = () => {
        setTimeout(() => { mostrarSugerencias.value = false; }, 200);
    };

    return {
        terminoBusqueda,
        estaCargando,
        mostrarSugerencias,
        sugerenciasFiltradas,
        elementoSeleccionado,
        seleccionar,
        cerrarSugerencias
    };
}