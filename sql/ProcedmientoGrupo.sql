select
	p.procCodigo,
	p.procDescripcion as procedimiento,
	p.prsgCodigo,
	psg.prgrCodigo,
	psg.prsgDescripcion as subgrupo,
	pg.prgrDescripcion as grupo
from Procedimiento p
	left join ProcedimientoSubGrupo psg on p.prsgCodigo = psg.prsgCodigo
	left join ProcedimientoGrupo pg on psg.prgrCodigo = pg.prgrCodigo
where
	p.procActivo = 1