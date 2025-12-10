USE ClinicaAdventista

declare @desde date = '2025-01-01'
declare @hasta date = '2025-12-31'

SELECT 
	convert(date,t.turnFecha) as fecha,
	t.mediCodigo,
	me.persApellido + ', ' + me.persNombre as medico,
	t.tetuCodigo,
	te.tetuDescripcion,
	t.procCodigo,
	pr.procDescripcion as procedimiento,
	t.mediCodigoEfector
	--,t.*
FROM Turno t
	left join TipoEstadoTurno te on t.tetuCodigo = te.tetuCodigo
	left join Procedimiento pr on t.procCodigo = pr.procCodigo
	left join Persona me on t.mediCodigo = me.persCodigo
where 
	t.turnFecha between @desde and @hasta
	and t.paciCodigo = 83
	and t.tetuCodigo = 4

-- select * from OrdenTurnoProcedimiento