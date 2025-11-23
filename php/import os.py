import os
from flask import Flask, render_template, request, redirect, url_for, session, abort
from functools import wraps
import mysql.connector
from mysql.connector import errorcode
from werkzeug.security import generate_password_hash, check_password_hash

# --- Configuración de la App (Ejemplo) ---
# En una app real, esto estaría en un archivo de configuración
app = Flask(__name__)
app.config['SECRET_KEY'] = os.environ.get('SECRET_KEY', 'una-llave-secreta-muy-fuerte')

# --- Simulación de Conexión a BD (¡Ajusta esto a tu app real!) ---
# Debes tener tu propia lógica para conectarte a la BD.
# Esta es solo una simulación para que el código sea completo.
def get_db_connection():
    try:
        # Reemplaza con tus credenciales reales
        conn = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",  # Tu contraseña de XAMPP/MySQL
            database="docentetrack" # Asumiendo el nombre de la BD
        )
        return conn
    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            print("Error: Usuario o contraseña de MySQL incorrectos")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            print("Error: La base de datos no existe")
        else:
            print(err)
        return None # Falló la conexión

# --- Simulación de Decorador @login_required ---
# Tu app ya debería tener esto, es solo un ejemplo.
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # El PHP original usa 'user', asumo que admin usa 'admin_user'
        if 'admin_user' not in session:
            # El PHP redirige a login-admin.html con un error
            # Usaremos flash o un query param, similar al PHP
            error_msg = 'Inicia sesion primero'
            return redirect(url_for('login_admin', e=error_msg)) # Asumiendo que tienes una ruta 'login_admin'
        return f(*args, **kwargs)
    return decorated_function

# --- Rutas de ejemplo (para que 'url_for' funcione) ---
@app.route('/admin/login')
def login_admin():
    # Simulación de la página de login
    error = request.args.get('e')
    return f"Página de Login Admin (Error: {error})"

# --- Ruta Principal Solicitada ---

@app.route('/admin/docentes', methods=['GET', 'POST'])
@login_required
def admin_docentes():
    
    mensaje = None
    error = None
    docentes = []
    
    conn = None
    cursor = None

    try:
        conn = get_db_connection()
        if conn is None:
            raise Exception("No se pudo conectar a la base de datos.")
            
        # Usamos dictionary=True para obtener resultados como diccionarios (similar a MYSQLI_ASSOC)
        cursor = conn.cursor(dictionary=True)

        # ========= Lógica POST (Crear, Editar, Cambiar Status) =========
        if request.method == 'POST':
            accion = request.form.get('accion')
            
            try:
                if accion == 'crear':
                    id_docente = request.form.get('id_docente', '').upper().strip()
                    nombre = request.form.get('nombre', '').strip()
                    cedula = request.form.get('cedula_profesional', '').strip()
                    tel = request.form.get('telefono', '').strip()
                    correo = request.form.get('correo_electronico', '').strip()
                    puesto = request.form.get('puesto', '').strip()

                    if not id_docente or not nombre:
                        raise Exception('ID Docente y Nombre son obligatorios')

                    # Contraseña inicial por defecto
                    status = 'Activo'
                    hashed_pass = generate_password_hash('Docente123*', method='pbkdf2:sha256') # Flask usa pbkdf2 por defecto

                    query = """
                        INSERT INTO docentes 
                            (id_docente, nombre, cedula_profesional, status, contrasena, telefono, correo_electronico, puesto)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                    """
                    cursor.execute(query, (id_docente, nombre, cedula, status, hashed_pass, tel, correo, puesto))
                    conn.commit()
                    mensaje = 'Docente creado (contraseña inicial: Docente123*)'

                elif accion == 'editar':
                    id_docente = request.form.get('id_docente', '').upper().strip()
                    nombre = request.form.get('nombre', '').strip()
                    cedula = request.form.get('cedula_profesional', '').strip()
                    tel = request.form.get('telefono', '').strip()
                    correo = request.form.get('correo_electronico', '').strip()
                    puesto = request.form.get('puesto', '').strip()
                    status = 'Inactivo' if request.form.get('status') == 'Inactivo' else 'Activo'
                    
                    try:
                        id_db = int(request.form.get('id', 0))
                    except ValueError:
                        raise Exception('ID de base de datos inválido')

                    if id_db <= 0 or not id_docente or not nombre:
                        raise Exception('Datos de edición inválidos')

                    query = """
                        UPDATE docentes
                        SET id_docente = %s, nombre = %s, cedula_profesional = %s, 
                            telefono = %s, correo_electronico = %s, puesto = %s, status = %s
                        WHERE id = %s
                    """
                    cursor.execute(query, (id_docente, nombre, cedula, tel, correo, puesto, status, id_db))
                    conn.commit()
                    mensaje = 'Docente actualizado'

                elif accion == 'status':
                    try:
                        id_db = int(request.form.get('id', 0))
                    except ValueError:
                        raise Exception('ID de base de datos inválido')
                        
                    nuevo_status = 'Inactivo' if request.form.get('nuevo') == 'Inactivo' else 'Activo'
                    
                    if id_db <= 0:
                        raise Exception('ID inválido')

                    query = "UPDATE docentes SET status = %s WHERE id = %s"
                    cursor.execute(query, (nuevo_status, id_db))
                    conn.commit()
                    mensaje = 'Estado actualizado'
            
            except mysql.connector.Error as db_err:
                # Error de BD (ej. duplicado)
                conn.rollback() # Revertir cambios
                error = f"Error de base de datos: {db_err.msg}"
            except Exception as e:
                # Error de validación
                error = str(e)

        # ========= Lógica GET (Listado) =========
        # Esto se ejecuta siempre (en GET, o después de un POST) para mostrar la lista actualizada.
        try:
            query_select = """
                SELECT id, id_docente, nombre, status, telefono, correo_electronico, puesto,
                       cedula_profesional, qr_semanal, qr_ultima_actualizacion
                FROM docentes
                ORDER BY id DESC
            """
            cursor.execute(query_select)
            docentes = cursor.fetchall()
        
        except mysql.connector.Error as e:
            # Si falla el listado, lo reportamos.
            # No sobrescribimos un error de POST si ya existía.
            if not error:
                error = f'Error al cargar docentes: {e.msg}'

    except Exception as e:
        # Error general (ej. conexión a BD)
        error = str(e)
        
    finally:
        # Asegurarnos de cerrar la conexión y el cursor
        if cursor:
            cursor.close()
        if conn:
            conn.close()

    # Renderizar la plantilla pasando los datos
    return render_template(
        'admin/docentes.html', # Asumiendo que tu plantilla está en templates/admin/docentes.html
        user=session.get('admin_user'),
        docentes=docentes,
        mensaje=mensaje,
        error=error
    )

# --- Punto de entrada (para pruebas) ---
if __name__ == '__main__':
    # Simular un usuario logueado para pruebas
    # En producción, esto no se hace.
    @app.route('/testlogin')
    def test_login():
        session['admin_user'] = {'username': 'testadmin', 'id': 1}
        return 'Sesión de admin simulada. <a href="/admin/docentes">Ir a Docentes</a>'

    app.run(debug=True) 