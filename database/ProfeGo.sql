CREATE DATABASE IF NOT EXISTS ProfeGo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
 
USE ProfeGo;

CREATE TABLE usuarios (
  id_usuario     INT NOT NULL AUTO_INCREMENT,
  nombres        VARCHAR(100) NOT NULL,
  apellidos      VARCHAR(100) NOT NULL,
  correo         VARCHAR(150) NOT NULL,
  telefono       VARCHAR(20)  NOT NULL,
  documento      VARCHAR(30)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  rol            ENUM('estudiante','docente','admin') NOT NULL,
  estado         ENUM('activo','suspendido','eliminado') NOT NULL DEFAULT 'activo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE (correo),
  UNIQUE (telefono),
  UNIQUE (documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE dispositivos (
  id_dispositivo INT NOT NULL AUTO_INCREMENT,
  id_usuario     INT NOT NULL,
  token_push     VARCHAR(255) NOT NULL,
  plataforma     ENUM('android','ios','web') NOT NULL,
  PRIMARY KEY (id_dispositivo),
  UNIQUE (token_push),
  INDEX idx_dispositivo_usuario (id_usuario),
  FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE perfiles_docente (
  id_docente           INT NOT NULL,
  estado_verificacion  ENUM('pendiente','verificado','rechazado') NOT NULL DEFAULT 'pendiente',
  calificacion_prom    DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  total_tutorias       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id_docente),
  FOREIGN KEY (id_docente) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE ubicacion_docente (
  id_docente    INT NOT NULL,
  en_linea      TINYINT(1) NOT NULL DEFAULT 0,
  latitud       DECIMAL(9,6) NULL,
  longitud      DECIMAL(9,6) NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_docente),
  INDEX idx_docente_cercano (en_linea, latitud, longitud),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE asignaturas (
  id_asignatura INT NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(100) NOT NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_asignatura),
  UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE docente_asignaturas (
  id_docente    INT NOT NULL,
  id_asignatura INT NOT NULL,
  precio_hora   INT NOT NULL,
  PRIMARY KEY (id_docente, id_asignatura),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente),
  FOREIGN KEY (id_asignatura) REFERENCES asignaturas (id_asignatura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE solicitudes (
  id_solicitud     INT NOT NULL AUTO_INCREMENT,
  id_estudiante    INT NOT NULL,
  id_asignatura    INT NOT NULL,
  tema             VARCHAR(150) NOT NULL,
  modalidad        ENUM('presencial','virtual') NOT NULL,
  latitud          DECIMAL(9,6) NULL,
  longitud         DECIMAL(9,6) NULL,
  duracion_minutos SMALLINT NOT NULL,
  precio_propuesto INT NOT NULL,
  fecha_deseada    DATETIME NULL,
  estado           ENUM('buscando','aceptada','cancelada','expirada') NOT NULL DEFAULT 'buscando',
  fecha_creacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_solicitud),
  INDEX idx_solicitud_busqueda (estado, id_asignatura, fecha_creacion),
  INDEX idx_solicitud_estudiante (id_estudiante, estado),
  FOREIGN KEY (id_estudiante) REFERENCES usuarios (id_usuario),
  FOREIGN KEY (id_asignatura) REFERENCES asignaturas (id_asignatura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE ofertas (
  id_oferta       INT NOT NULL AUTO_INCREMENT,
  id_solicitud    INT NOT NULL,
  id_docente      INT NOT NULL,
  precio_ofertado INT NOT NULL,
  estado          ENUM('pendiente','aceptada','rechazada','retirada') NOT NULL DEFAULT 'pendiente',
  fecha_oferta    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_oferta),
  UNIQUE KEY uq_oferta_docente (id_solicitud, id_docente),
  INDEX idx_oferta_docente (id_docente, estado),
  FOREIGN KEY (id_solicitud) REFERENCES solicitudes (id_solicitud),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE tutorias (
  id_tutoria      INT NOT NULL AUTO_INCREMENT,
  id_solicitud    INT NOT NULL,
  id_oferta       INT NOT NULL,
  id_estudiante   INT NOT NULL,
  id_docente      INT NOT NULL,
  precio_final    INT NOT NULL,
  estado          ENUM('aceptada','en_camino','en_curso','finalizada','cancelada')
                  NOT NULL DEFAULT 'aceptada',
  fecha_aceptada  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_inicio    DATETIME NULL,
  fecha_fin       DATETIME NULL,
  cancelada_por   ENUM('estudiante','docente') NULL,
  PRIMARY KEY (id_tutoria),
  UNIQUE (id_solicitud),
  UNIQUE (id_oferta),
  INDEX idx_tutoria_docente (id_docente, estado),
  INDEX idx_tutoria_estudiante (id_estudiante, estado),
  FOREIGN KEY (id_solicitud) REFERENCES solicitudes (id_solicitud),
  FOREIGN KEY (id_oferta) REFERENCES ofertas (id_oferta),
  FOREIGN KEY (id_estudiante) REFERENCES usuarios (id_usuario),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE pagos (
  id_pago      INT NOT NULL AUTO_INCREMENT,
  id_tutoria   INT NOT NULL,
  monto        INT NOT NULL,
  comision     INT NOT NULL DEFAULT 0,
  metodo       ENUM('efectivo','tarjeta','transferencia') NOT NULL,
  estado       ENUM('pendiente','pagado','fallido','reembolsado') NOT NULL DEFAULT 'pendiente',
  fecha_pago   DATETIME NULL,
  PRIMARY KEY (id_pago),
  UNIQUE (id_tutoria),
  FOREIGN KEY (id_tutoria) REFERENCES tutorias (id_tutoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE calificaciones (
  id_calificacion INT NOT NULL AUTO_INCREMENT,
  id_tutoria      INT NOT NULL,
  id_evaluador    INT NOT NULL,
  id_evaluado     INT NOT NULL,
  puntuacion      TINYINT UNSIGNED NOT NULL,
  comentario      VARCHAR(500) NULL,
  PRIMARY KEY (id_calificacion),
  UNIQUE KEY uq_calificacion (id_tutoria, id_evaluador),
  INDEX idx_calificacion_evaluado (id_evaluado),
  FOREIGN KEY (id_tutoria) REFERENCES tutorias (id_tutoria),
  FOREIGN KEY (id_evaluador) REFERENCES usuarios (id_usuario),
  FOREIGN KEY (id_evaluado) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE mensajes (
  id_mensaje   INT NOT NULL AUTO_INCREMENT,
  id_tutoria   INT NOT NULL,
  id_remitente INT NOT NULL,
  contenido    VARCHAR(1000) NOT NULL,
  fecha_envio  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mensaje),
  INDEX idx_mensaje_tutoria (id_tutoria, fecha_envio),
  FOREIGN KEY (id_tutoria) REFERENCES tutorias (id_tutoria),
  FOREIGN KEY (id_remitente) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;