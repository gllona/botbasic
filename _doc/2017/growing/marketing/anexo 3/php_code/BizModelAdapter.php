<?php

class BizModelAdapter implements BizModelAdapterIface
{

    // todos los metodos publicos deben retornar arreglos aunque devuelvan un unico valor

    static public function crearLote ()
    {
        return [ Lote::crear() ];
    }

    static public function solicitudesDeLote ($idLote)
    {
        return [ self::serializar(Lote::solicitudes($idLote)) ];
    }

    static public function recibirLote ($idLote, $idEmpleado)
    {
        Lote::recibir($idLote, $idEmpleado);
        return [];
    }

    static public function generarSolicitud ($idEmpleado, $idInsumo, $cantidad, $plazo, $observaciones)
    {
        return [ Solicitud::generar($idEmpleado, $idInsumo, $cantidad, $plazo, $observaciones) ];
    }

    static public function aprobarSolicitud ($idSolicitud, $idEmpleado)
    {
        Solicitud::aprobar($idSolicitud, $idEmpleado);
        return [];
    }

    static public function negarSolicitud ($idSolicitud, $idEmpleado)
    {
        Solicitud::negar($idSolicitud, $idEmpleado);
        return [];
    }

    static public function despacharSolicitud ($idSolicitud)
    {
        Solicitud::despachar($idSolicitud);
        return [];
    }

    static public function incluirSolicitudEnLote ($idSolicitud, $idLote)
    {
        Solicitud::incluirEnLote($idSolicitud, $idLote);
        return [];
    }

    static public function solicitudesEnRango($diasMinimos, $diasMaximos)
    {
        return [ self::serializar(Solicitud::enRango($diasMinimos, $diasMaximos)) ];
    }

    static public function datosDeSolicitud ($idSolicitud)
    {
        return Solicitud::datos($idSolicitud);
    }

    static public function validarUsuario ($celular)
    {
        return [ Empleado::esValido($celular) ];
    }

    static public function puedeUsuarioAprobarNegar ($idEmpleado)
    {
        return [ Empleado::puedeAprobarNegar($idEmpleado) ? 1 : 0 ];
    }

    static public function puedeUsuarioRecibir ($idEmpleado)
    {
        return [ Empleado::puedeRecibir($idEmpleado) ? 1 : 0 ];
    }

    static public function listarCategorias ()
    {
        return Categoria::listar();
    }

    static public function listarInsumos ($idCategoria)
    {
        return Insumo::listar($idCategoria);
    }

    static public function listarPlazos ()
    {
        return Solicitud::plazos();
    }

    static public function usuarioAprobador ($idEmpleado)
    {
        return [ Empleado::aprobador($idEmpleado) ];
    }

    static public function estadoSolicitud ($idSolicitud)
    {
        return [ Solicitud::estado($idSolicitud) ];
    }

    static public function loteEsValido ($idLote)
    {
        return [ Lote::esValido($idLote) ? 1 : 0 ];
    }

    static public function puedeUsuarioRecibirEsteLote ($idLote, $idEmpleado)
    {
        return [ Empleado::puedeRecibirLote($idEmpleado, $idLote) ? 1 : 0 ];
    }

    static private function serializar ($arreglo, $separador = '|')
    {
        return join($separador, $arreglo);
    }

}

