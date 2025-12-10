USE ClinicaAdventista

declare @desde date = '2025-01-01'
declare @hasta date = '2025-12-31'

select
	ot.ortuCodigo as idOrden,
	convert(date,ot.ortuFecha) as fecha,
	ot.paciCodigo,
	pa.persApellido + ', ' + pa.persNombre as paciente,
	otp.procCodigo,
	pr.procDescripcion as procedimiento,
	ot.mediCodigo,
	me.persApellido + ', ' + me.persNombre as medico,
	pg.prgrCodigo as idGrupo,
	pg.prgrDescripcion as grupo
from OrdenTurnoProcedimiento otp
	left join Procedimiento pr on otp.procCodigo = pr.procCodigo
	left join OrdenTurno ot on otp.ortuCodigo = ot.ortuCodigo
	left join Persona me on ot.mediCodigo = me.persCodigo
	left join Persona pa on ot.paciCodigo = pa.persCodigo
	left join ProcedimientoSubGrupo psg on pr.prsgCodigo = psg.prsgCodigo
	left join ProcedimientoGrupo pg on psg.prgrCodigo = pg.prgrCodigo
where 
	ot.mediCodigo not in (20732,6)
	and ot.ortuFecha between @desde and @hasta
group by
	ot.ortuCodigo,
	ot.ortuFecha,
	ot.mediCodigo,
	ot.paciCodigo,
	pa.persApellido,
	pa.persNombre,
	otp.procCodigo,
	pr.procDescripcion,
	me.persApellido,
	me.persNombre,
	pg.prgrCodigo,
	pg.prgrDescripcion
order by 
	pg.prgrDescripcion,
	ot.ortuFecha