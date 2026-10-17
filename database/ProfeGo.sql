CREATE DATABASE IF NOT EXISTS ProfeGo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ProfeGo;

CREATE TABLE usuarios (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL,
  apellido VARCHAR(30) NOT NULL,
  correo VARCHAR(150) NOT NULL,
  telefono VARCHAR(20) NOT NULL,
  documento VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('estudiante','docente','admin') NOT NULL,
  estado ENUM('activo','suspendido','eliminado') NOT NULL DEFAULT 'activo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo),
  UNIQUE KEY uq_usuarios_telefono (telefono),
  UNIQUE KEY uq_usuarios_documento (documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cuentas de estudiantes, docentes y administradores de ProfeGo.';

CREATE TABLE dispositivos (
  id INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  token_push VARCHAR(255) NOT NULL,
  plataforma ENUM('mobile','web') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dispositivo_token (token_push),
  KEY idx_dispositivo_usuario (id_usuario),
  CONSTRAINT fk_dispositivo_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dispositivos asociados a usuarios para notificaciones push.';

CREATE TABLE perfiles_docente (
  id_docente INT NOT NULL,
  estado_verificacion ENUM('pendiente','verificado','rechazado') NOT NULL DEFAULT 'pendiente',
  calificacion_prom DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  total_tutoria INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id_docente),
  CONSTRAINT fk_perfil_usuario FOREIGN KEY (id_docente) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Datos adicionales de verificación y resumen para usuarios con rol docente.';

CREATE TABLE ubicaciones_docente (
  id_docente INT NOT NULL,
  en_linea TINYINT(1) NOT NULL DEFAULT 0,
  latitud DECIMAL(9,6) NULL,
  longitud DECIMAL(9,6) NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_docente),
  KEY idx_docente_cercano (en_linea, latitud, longitud),
  CONSTRAINT fk_ubicacion_perfil FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Ubicación y disponibilidad en línea de cada docente.';

CREATE TABLE asignaturas (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(50) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asignatura_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catálogo de asignaturas disponibles para tutorías.';

CREATE TABLE docente_asignaturas (
  id_docente INT NOT NULL,
  id_asignatura INT NOT NULL,
  precio_hora INT NOT NULL,
  PRIMARY KEY (id_docente, id_asignatura),
  CONSTRAINT fk_docente_asignatura_perfil FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente),
  CONSTRAINT fk_docente_asignatura_asignatura FOREIGN KEY (id_asignatura) REFERENCES asignaturas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Asignaturas que ofrece cada docente y su precio por hora.';

CREATE TABLE solicitudes (
  id INT NOT NULL AUTO_INCREMENT,
  id_estudiante INT NOT NULL,
  id_asignatura INT NOT NULL,
  tema VARCHAR(100) NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL,
  latitud DECIMAL(9,6) NULL,
  longitud DECIMAL(9,6) NULL,
  duracion_minuto SMALLINT NOT NULL,
  precio_propuesto INT NOT NULL,
  fecha_deseada DATETIME NULL,
  estado ENUM('buscando','aceptada','cancelada','expirada') NOT NULL DEFAULT 'buscando',
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_solicitud_busqueda (estado, id_asignatura, fecha_creacion),
  KEY idx_solicitud_estudiante (id_estudiante, estado),
  CONSTRAINT fk_solicitud_estudiante FOREIGN KEY (id_estudiante) REFERENCES usuarios (id),
  CONSTRAINT fk_solicitud_asignatura FOREIGN KEY (id_asignatura) REFERENCES asignaturas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Solicitudes de tutoría creadas por estudiantes.';

CREATE TABLE ofertas (
  id INT NOT NULL AUTO_INCREMENT,
  id_solicitud INT NOT NULL,
  id_docente INT NOT NULL,
  precio_ofertado INT NOT NULL,
  estado ENUM('pendiente','aceptada','rechazada','retirada') NOT NULL DEFAULT 'pendiente',
  fecha_oferta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oferta_docente (id_solicitud, id_docente),
  UNIQUE KEY uq_oferta_referencia_tutoria (id, id_solicitud, id_docente),
  KEY idx_oferta_docente (id_docente, estado),
  CONSTRAINT fk_oferta_solicitud FOREIGN KEY (id_solicitud) REFERENCES solicitudes (id),
  CONSTRAINT fk_oferta_docente FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Propuestas de docentes para atender solicitudes de tutoría.';

CREATE TABLE tutorias (
  id INT NOT NULL AUTO_INCREMENT,
  id_solicitud INT NOT NULL,
  id_oferta INT NOT NULL,
  id_estudiante INT NOT NULL,
  id_docente INT NOT NULL,
  precio_final INT NOT NULL,
  estado ENUM('aceptada','en_camino','en_curso','finalizada','cancelada') NOT NULL DEFAULT 'aceptada',
  fecha_aceptada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_inicio DATETIME NULL,
  fecha_fin DATETIME NULL,
  cancelada_por ENUM('estudiante','docente') NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tutoria_solicitud (id_solicitud),
  UNIQUE KEY uq_tutoria_oferta (id_oferta),
  KEY idx_tutoria_docente (id_docente, estado),
  KEY idx_tutoria_estudiante (id_estudiante, estado),
  CONSTRAINT fk_tutoria_oferta_coherente FOREIGN KEY (id_oferta, id_solicitud, id_docente) REFERENCES ofertas (id, id_solicitud, id_docente),
  CONSTRAINT fk_tutoria_solicitud FOREIGN KEY (id_solicitud) REFERENCES solicitudes (id),
  CONSTRAINT fk_tutoria_estudiante FOREIGN KEY (id_estudiante) REFERENCES usuarios (id),
  CONSTRAINT fk_tutoria_docente FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tutorías confirmadas entre un estudiante y un docente.';

CREATE TABLE pagos (
  id INT NOT NULL AUTO_INCREMENT,
  id_tutoria INT NOT NULL,
  monto INT NOT NULL,
  comision INT NOT NULL DEFAULT 0,
  metodo ENUM('efectivo','tarjeta','transferencia') NOT NULL,
  estado ENUM('pendiente','pagado','fallido','reembolsado') NOT NULL DEFAULT 'pendiente',
  fecha_pago DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pago_tutoria (id_tutoria),
  CONSTRAINT fk_pago_tutoria FOREIGN KEY (id_tutoria) REFERENCES tutorias (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Información y estado del pago asociado a una tutoría.';

CREATE TABLE calificaciones (
  id INT NOT NULL AUTO_INCREMENT,
  id_tutoria INT NOT NULL,
  id_evaluador INT NOT NULL,
  id_evaluado INT NOT NULL,
  puntuacion TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_calificacion (id_tutoria, id_evaluador),
  KEY idx_calificacion_evaluado (id_evaluado),
  CONSTRAINT fk_calificacion_tutoria FOREIGN KEY (id_tutoria) REFERENCES tutorias (id),
  CONSTRAINT fk_calificacion_evaluador FOREIGN KEY (id_evaluador) REFERENCES usuarios (id),
  CONSTRAINT fk_calificacion_evaluado FOREIGN KEY (id_evaluado) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Puntuaciones y comentarios sobre participantes de una tutoría.';

CREATE TABLE mensajes (
  id INT NOT NULL AUTO_INCREMENT,
  id_tutoria INT NOT NULL,
  id_remitente INT NOT NULL,
  contenido VARCHAR(1000) NOT NULL,
  fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mensaje_tutoria (id_tutoria, fecha_envio),
  CONSTRAINT fk_mensaje_tutoria FOREIGN KEY (id_tutoria) REFERENCES tutorias (id),
  CONSTRAINT fk_mensaje_remitente FOREIGN KEY (id_remitente) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Mensajes enviados durante una tutoría.';